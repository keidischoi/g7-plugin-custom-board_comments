<?php

namespace Plugins\Custom\BoardComments\Services;

use Illuminate\Support\Facades\DB;
use Plugins\Custom\BoardComments\Support\BoardEnv;
use Plugins\Custom\BoardComments\Support\LinkTable;
use Plugins\Custom\BoardComments\Support\ReplaceRules;

/**
 * 0.2.0 옮기기: 공식 게시판 댓글 → custom-comments 댓글(board_post).
 *
 * - 원본 공식 댓글은 절대 지우거나 고치지 않습니다 (읽기만).
 * - 짝 표(origin=migrate)에 남겨 두 번 돌려도 같은 댓글을 다시 만들지 않습니다.
 *   이 짝 덕분에 옮긴 댓글을 custom-comments 에서 고치거나 지우면 원래 공식 댓글이 바뀝니다 (복제 없음).
 * - 이미 「똑같이 남기기」 로 만들어진 공식 댓글(origin=mirror)도 짝이 있으므로 건너뜁니다 (되돌이 없음).
 * - 작성자 · 작성 시각 · 답글 관계를 그대로. 비회원 댓글은 회원 번호 0 + 짝 표의 이름으로 보입니다.
 * - 건너뜀: 지운 댓글 · 블라인드 댓글 · 지운 글의 댓글 · 비밀 댓글(--include-secret 없으면) · 빈 글.
 *   부모가 건너뛰어진 답글은 최상위 댓글로 옮깁니다.
 */
final class Migrator
{
    /**
     * @param  list<string>  $slugs
     * @param  callable(string): void|null  $log
     * @return array<string, int>
     */
    public function run(array $slugs, bool $dryRun = false, ?int $postId = null, bool $includeSecret = false, ?callable $log = null): array
    {
        $log ??= static function (string $line): void {};
        $stats = ['boards' => 0, 'seen' => 0, 'migrated' => 0, 'already' => 0, 'skipped_secret' => 0, 'skipped_status' => 0, 'skipped_post' => 0, 'skipped_empty' => 0, 'orphan_replies' => 0, 'errors' => 0];
        if (! BoardEnv::commentsTableReady()) {
            throw new \RuntimeException('custom-comments 댓글 표(digital_product_comments.target_type)가 없습니다. custom-comments 1.0.0+ 를 먼저 설치 · 활성화하세요.');
        }
        if (! $dryRun && ! LinkTable::ensure()) {
            throw new \RuntimeException('짝 표(custom_board_comment_links)를 만들지 못했습니다.');
        }
        $linkReady = LinkTable::ensure();

        foreach ($slugs as $slug) {
            $board = BoardEnv::boardBySlug($slug);
            if (! $board) {
                $log("게시판 없음: {$slug}");

                continue;
            }
            $stats['boards']++;
            $boardId = (int) $board->id;
            $log("게시판 {$slug} (#{$boardId})".($dryRun ? ' — 미리 보기' : ''));

            /** @var array<int, int> $map 공식 댓글 번호 → custom 댓글 번호 (미리 보기는 가짜 번호) */
            $map = [];
            $fake = -1;
            $q = DB::table('board_comments')->where('board_id', $boardId)->orderBy('id');
            if ($postId) {
                $q->where('post_id', $postId);
            }
            foreach ($q->cursor() as $c) {
                $stats['seen']++;
                $cid = (int) $c->id;
                if ($linkReady) {
                    $link = DB::table(LinkTable::NAME)->where('board_comment_id', $cid)->first();
                    if ($link) {
                        $stats['already']++;
                        if ($link->custom_comment_id !== null) {
                            $map[$cid] = (int) $link->custom_comment_id;
                        }

                        continue;
                    }
                }
                $status = $c->status instanceof \BackedEnum ? $c->status->value : (string) ($c->status ?? 'published');
                if (! empty($c->deleted_at) || $status !== 'published') {
                    $stats['skipped_status']++;

                    continue;
                }
                if (! empty($c->is_secret) && ! $includeSecret) {
                    $stats['skipped_secret']++;

                    continue;
                }
                $post = BoardEnv::post((int) $c->post_id);
                if (! $post || BoardEnv::postDeleted($post)) {
                    $stats['skipped_post']++;

                    continue;
                }
                $body = ReplaceRules::boardTextToBody((string) ($c->content ?? ''));
                if ($body === '') {
                    $stats['skipped_empty']++;

                    continue;
                }
                $parent = 0;
                $bp = (int) ($c->parent_id ?? 0);
                if ($bp > 0) {
                    if (isset($map[$bp])) {
                        $parent = $map[$bp];
                    } else {
                        $stats['orphan_replies']++;
                    }
                }
                if ($dryRun) {
                    $map[$cid] = $fake--;
                    $stats['migrated']++;

                    continue;
                }
                try {
                    DB::transaction(function () use ($c, $parent, $body, $boardId, &$map, $cid): void {
                        $now = date('Y-m-d H:i:s');
                        $newId = (int) DB::table(MirrorService::COMMENTS_TABLE)->insertGetId([
                            'target_type' => ReplaceRules::TARGET,
                            'digital_product_id' => (int) $c->post_id,
                            'user_id' => (int) ($c->user_id ?? 0),
                            'parent_id' => max(0, $parent),
                            'body' => $body,
                            'image_path' => null,
                            'sticker_path' => null,
                            'created_at' => $c->created_at ?? $now,
                            'updated_at' => $c->updated_at ?? ($c->created_at ?? $now),
                        ]);
                        $snap = DB::table(MirrorService::COMMENTS_TABLE)->where('id', $newId)->first();
                        DB::table(LinkTable::NAME)->insert([
                            'board_id' => $boardId,
                            'post_id' => (int) $c->post_id,
                            'custom_comment_id' => $newId,
                            'board_comment_id' => $cid,
                            'origin' => 'migrate',
                            'state' => 'active',
                            'author_name' => empty($c->user_id) ? mb_substr((string) ($c->author_name ?? ''), 0, 50) : null,
                            'snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $map[$cid] = $newId;
                    });
                    $stats['migrated']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $log("  실패 #{$cid}: ".$e->getMessage());
                }
            }
        }

        return $stats;
    }

    /**
     * 옮길 게시판 목록: --board 가 있으면 그것, 없으면 설정의 바꾸기 게시판 (* 면 모든 게시판).
     *
     * @param  list<string>  $option
     * @return list<string>
     */
    public static function resolveSlugs(array $option): array
    {
        $slugs = [];
        foreach ($option as $o) {
            foreach (preg_split('/[\s,]+/', (string) $o) ?: [] as $s) {
                $s = strtolower(trim($s));
                if ($s !== '' && ! in_array($s, $slugs, true)) {
                    $slugs[] = $s;
                }
            }
        }
        if ($slugs !== []) {
            return $slugs;
        }
        $list = \Plugins\Custom\BoardComments\Support\SettingsRules::parseReplaceSlugs(BoardEnv::settings()['replace_slugs'] ?? '');
        if ($list === ['*']) {
            try {
                return DB::table('boards')->orderBy('id')->pluck('slug')->map(static fn ($s): string => (string) $s)->all();
            } catch (\Throwable) {
                return [];
            }
        }

        return $list;
    }
}

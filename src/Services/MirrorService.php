<?php

namespace Plugins\Custom\BoardComments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Plugins\Custom\BoardComments\Contracts\BoardCommentGateway;
use Plugins\Custom\BoardComments\Support\BoardEnv;
use Plugins\Custom\BoardComments\Support\LinkTable;
use Plugins\Custom\BoardComments\Support\ReplaceRules;

/**
 * 0.2.0 바꾸기 모드의 짝 맞추기.
 *
 * custom-comments → 공식 댓글 (「게시판 댓글에 똑같이 남기기」 켜짐일 때만):
 *   새 댓글 · 고침 · 지움을 sirsoft-board CommentService 로 그대로 남깁니다 (댓글 수 · 알림 · 관리 · 검색 · 플러그인을 꺼도 남음).
 * 공식 댓글 → custom-comments (같은 설정일 때만, 짝이 있는 댓글만):
 *   관리자가 공식 쪽에서 지우거나 블라인드하면 custom-comments 쪽도 숨기고, 복원하면 되살립니다.
 * 게시글 삭제 · 복원 (설정과 관계없이, board_post 댓글이 있으면):
 *   지울 때 custom-comments 댓글을 짝 표에 보관한 뒤 custom-comments.target.deleted 로 비우고, 복원하면 되살립니다.
 *
 * 되돌이 막기: 이쪽이 CommentService 를 부르는 동안($busy) 공식 댓글 훅은 무시합니다.
 * 옮기기 명령은 짝 표에 origin=migrate 로 남기므로, 그 댓글을 고치거나 지우면 원래 공식 댓글이 바뀝니다 (새로 만들지 않음).
 */
final class MirrorService
{
    public const COMMENTS_TABLE = 'digital_product_comments';

    private static bool $busy = false;

    private static ?BoardCommentGateway $gatewayOverride = null;

    /** 검사용 */
    public static function useGateway(?BoardCommentGateway $gateway): void
    {
        self::$gatewayOverride = $gateway;
    }

    public static function busy(): bool
    {
        return self::$busy;
    }

    // ── custom-comments → 공식 댓글 ─────────────────────────────────────────

    public function customCreated(int $customId, string $type, int $postId, int $userId, int $parentId, int $depth = 0): ?int
    {
        if ($type !== ReplaceRules::TARGET || $customId <= 0 || $depth > 3) {
            return null;
        }
        [$post, $board] = $this->postAndBoard($postId);
        if (! $post || ! $board || ! BoardEnv::mirrorActive((string) $board->slug) || ! LinkTable::ensure()) {
            return null;
        }
        if (! ($board->use_comment ?? true) || BoardEnv::postDeleted($post)) {
            return null;
        }
        $existing = $this->linkByCustom($customId);
        if ($existing) {
            return $existing->board_comment_id !== null ? (int) $existing->board_comment_id : null;
        }
        $row = DB::table(self::COMMENTS_TABLE)->where('id', $customId)->first();
        if (! $row || (string) ($row->target_type ?? '') !== ReplaceRules::TARGET) {
            return null;
        }
        $slug = (string) $board->slug;

        // 부모: 짝이 있으면 그 공식 댓글, 없으면(부모가 남기기 전 댓글) 부모부터 남겨 봄
        $boardParent = 0;
        $customParent = $parentId > 0 ? $parentId : (int) ($row->parent_id ?? 0);
        if ($customParent > 0) {
            $plink = $this->linkByCustom($customParent);
            $pid = $plink && $plink->board_comment_id !== null ? (int) $plink->board_comment_id : null;
            if ($pid === null && ! $plink) {
                $prow = DB::table(self::COMMENTS_TABLE)->where('id', $customParent)->first();
                if ($prow) {
                    $pid = $this->customCreated($customParent, $type, $postId, (int) $prow->user_id, (int) ($prow->parent_id ?? 0), $depth + 1);
                }
            }
            if ($pid) {
                $boardParent = ReplaceRules::pickBoardParent($this->boardChain($pid), max(0, (int) ($board->max_comment_depth ?? 10)));
            }
        }

        $data = [
            'post_id' => $postId,
            'user_id' => $userId > 0 ? $userId : ((int) $row->user_id > 0 ? (int) $row->user_id : null),
            'parent_id' => $boardParent > 0 ? $boardParent : null,
            'content' => $this->boardText($row),
            'is_secret' => false,
            'ip_address' => $this->ip(),
        ];
        $boardId = $this->callGateway(fn (BoardCommentGateway $g): int => $g->create($slug, $data));
        if ($boardId === null && $data['parent_id'] !== null) {
            // 깊이 한도 등으로 거부되면 최상위 댓글로 한 번 더
            $data['parent_id'] = null;
            $boardId = $this->callGateway(fn (BoardCommentGateway $g): int => $g->create($slug, $data));
        }
        if (! $boardId) {
            return null;
        }
        $this->insertLink([
            'board_id' => (int) $board->id,
            'post_id' => $postId,
            'custom_comment_id' => $customId,
            'board_comment_id' => $boardId,
            'origin' => 'mirror',
            'state' => 'active',
            'snapshot' => $this->snapshot($row),
        ]);

        return $boardId;
    }

    public function customUpdated(int $customId, string $type, int $postId): bool
    {
        if ($type !== ReplaceRules::TARGET || $customId <= 0 || ! LinkTable::ensure()) {
            return false;
        }
        [$post, $board] = $this->postAndBoard($postId);
        if (! $post || ! $board || ! BoardEnv::mirrorActive((string) $board->slug)) {
            return false;
        }
        $link = $this->linkByCustom($customId);
        $row = DB::table(self::COMMENTS_TABLE)->where('id', $customId)->first();
        if (! $link || $link->board_comment_id === null || $link->state !== 'active' || ! $row) {
            return false;
        }
        $ok = $this->callGateway(function (BoardCommentGateway $g) use ($board, $link, $postId, $row): int {
            $g->update((string) $board->slug, (int) $link->board_comment_id, $postId, $this->boardText($row));

            return 1;
        });
        DB::table(LinkTable::NAME)->where('id', $link->id)->update(['snapshot' => $this->snapshot($row), 'updated_at' => $this->now()]);

        return $ok !== null;
    }

    /**
     * @param  list<int>  $customIds
     */
    public function customDeleted(array $customIds, string $type, int $postId, int $actorId): int
    {
        if ($type !== ReplaceRules::TARGET || $customIds === [] || ! LinkTable::ensure()) {
            return 0;
        }
        [$post, $board] = $this->postAndBoard($postId);
        if (! $post || ! $board || ! BoardEnv::mirrorActive((string) $board->slug)) {
            return 0;
        }
        $done = 0;
        // 답글부터 (목록은 부모가 먼저)
        foreach (array_reverse(array_values(array_unique(array_map('intval', $customIds)))) as $cid) {
            $link = $this->linkByCustom($cid);
            if (! $link || $link->board_comment_id === null || $link->state !== 'active') {
                continue;
            }
            $snap = json_decode((string) $link->snapshot, true);
            $author = is_array($snap) ? (int) ($snap['user_id'] ?? 0) : 0;
            $trigger = $actorId > 0 && $author > 0 && $actorId !== $author ? 'admin' : 'user';
            $this->callGateway(function (BoardCommentGateway $g) use ($board, $link, $postId, $trigger): int {
                $g->delete((string) $board->slug, (int) $link->board_comment_id, $postId, $trigger);

                return 1;
            });
            DB::table(LinkTable::NAME)->where('id', $link->id)->update(['state' => 'custom_deleted', 'updated_at' => $this->now()]);
            $done++;
        }

        return $done;
    }

    // ── 공식 댓글 → custom-comments (짝 있는 댓글만) ───────────────────────────

    public function boardCommentGone(int $boardCommentId, string $slug, string $state): bool
    {
        if (self::$busy || $boardCommentId <= 0 || ! BoardEnv::mirrorActive($slug) || ! LinkTable::ensure()) {
            return false;
        }
        $link = DB::table(LinkTable::NAME)->where('board_comment_id', $boardCommentId)->first();
        if (! $link || $link->state !== 'active' || $link->custom_comment_id === null) {
            return false;
        }
        $row = DB::table(self::COMMENTS_TABLE)->where('id', (int) $link->custom_comment_id)->first();
        $update = ['state' => $state, 'updated_at' => $this->now()];
        if ($row) {
            $update['snapshot'] = $this->snapshot($row);
            $this->deleteCustomRows([(int) $row->id]);
        }
        DB::table(LinkTable::NAME)->where('id', $link->id)->update($update);

        return true;
    }

    public function boardCommentRestored(int $boardCommentId, string $slug): bool
    {
        if (self::$busy || $boardCommentId <= 0 || ! BoardEnv::mirrorActive($slug) || ! LinkTable::ensure()) {
            return false;
        }
        $link = DB::table(LinkTable::NAME)->where('board_comment_id', $boardCommentId)->first();
        if (! $link || ! in_array($link->state, ['board_deleted', 'board_blinded'], true)) {
            return false;
        }

        return $this->reviveLinks([$link]) > 0;
    }

    // ── 게시글 삭제 · 복원 ───────────────────────────────────────────────────

    /** 글을 지울 때: custom-comments 댓글을 짝 표에 보관 → 이미지 보관 → custom-comments.target.deleted */
    public function postDeleted(int $postId): int
    {
        if ($postId <= 0 || ! BoardEnv::commentsTableReady()) {
            return 0;
        }
        $rows = DB::table(self::COMMENTS_TABLE)
            ->where('target_type', ReplaceRules::TARGET)
            ->where('digital_product_id', $postId)
            ->orderBy('id')
            ->get();
        if ($rows->isEmpty()) {
            return 0;
        }
        if (! LinkTable::ensure()) {
            return 0;   // 보관할 곳이 없으면 비우지 않음 (복원 때 되살릴 수 없게 되므로)
        }
        $post = BoardEnv::post($postId);
        $boardId = $post ? (int) $post->board_id : 0;
        foreach ($rows as $row) {
            $link = $this->linkByCustom((int) $row->id);
            if ($link) {
                DB::table(LinkTable::NAME)->where('id', $link->id)->update([
                    'state' => 'purged',
                    'snapshot' => $this->snapshot($row),
                    'updated_at' => $this->now(),
                ]);
            } else {
                $this->insertLink([
                    'board_id' => $boardId,
                    'post_id' => $postId,
                    'custom_comment_id' => (int) $row->id,
                    'board_comment_id' => null,
                    'origin' => 'snapshot',
                    'state' => 'purged',
                    'snapshot' => $this->snapshot($row),
                ]);
            }
        }
        $this->copyDir($this->uploadDir($postId), $this->trashDir($postId));
        try {
            if (class_exists(\App\Extension\HookManager::class)) {
                \App\Extension\HookManager::doAction('custom-comments.target.deleted', ReplaceRules::TARGET, $postId);
            }
        } catch (\Throwable $e) {
            $this->warn('글 삭제 정리 실패', $e);
        }

        return $rows->count();
    }

    /** 글을 복원할 때: 보관한 댓글을 되살림 (지운 사람이 따로 지운 댓글은 보관되지 않았으므로 그대로 사라짐) */
    public function postRestored(int $postId): int
    {
        if ($postId <= 0 || ! BoardEnv::commentsTableReady() || ! LinkTable::ensure()) {
            return 0;
        }
        $links = DB::table(LinkTable::NAME)->where('post_id', $postId)->where('state', 'purged')->orderBy('custom_comment_id')->orderBy('id')->get()->all();
        if ($links === []) {
            return 0;
        }
        $this->copyDir($this->trashDir($postId), $this->uploadDir($postId));

        return $this->reviveLinks($links);
    }

    // ── 공용 ────────────────────────────────────────────────────────────────

    /**
     * snapshot 으로 custom-comments 댓글을 다시 만들고 짝을 새 번호로 고칩니다. 부모는 예전 번호 → 새 번호로 이어 줌.
     *
     * @param  list<object>  $links
     */
    private function reviveLinks(array $links): int
    {
        $map = [];
        $n = 0;
        foreach ($links as $link) {
            $snap = json_decode((string) $link->snapshot, true);
            if (! is_array($snap)) {
                continue;
            }
            $oldId = (int) ($snap['id'] ?? 0);
            $parent = (int) ($snap['parent_id'] ?? 0);
            if ($parent > 0) {
                if (isset($map[$parent])) {
                    $parent = $map[$parent];
                } elseif (! DB::table(self::COMMENTS_TABLE)->where('id', $parent)->exists()) {
                    $parent = 0;
                }
            }
            $insert = [
                'target_type' => ReplaceRules::TARGET,
                'digital_product_id' => (int) $link->post_id,
                'user_id' => (int) ($snap['user_id'] ?? 0),
                'parent_id' => $parent,
                'body' => (string) ($snap['body'] ?? ''),
                'image_path' => $snap['image_path'] ?? null,
                'sticker_path' => $snap['sticker_path'] ?? null,
                'created_at' => $snap['created_at'] ?? $this->now(),
                'updated_at' => $snap['updated_at'] ?? $this->now(),
            ];
            if ($this->hasCommentColumn('chat_sticker')) {
                $insert['chat_sticker'] = $snap['chat_sticker'] ?? null;
            }
            $newId = (int) DB::table(self::COMMENTS_TABLE)->insertGetId($insert);
            if ($oldId > 0) {
                $map[$oldId] = $newId;
            }
            DB::table(LinkTable::NAME)->where('id', $link->id)->update([
                'custom_comment_id' => $newId,
                'state' => 'active',
                'updated_at' => $this->now(),
            ]);
            $n++;
        }

        return $n;
    }

    /** custom-comments 쪽 댓글을 직접 지움 (좋아요 포함, 올린 이미지는 글 단위로 남겨 둠 — 되살릴 수 있도록) */
    private function deleteCustomRows(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        try {
            DB::table('digital_comment_likes')->whereIn('comment_id', $ids)->delete();
        } catch (\Throwable) {
        }
        DB::table(self::COMMENTS_TABLE)->whereIn('id', $ids)->delete();
    }

    /** @return array{0: ?object, 1: ?object} */
    private function postAndBoard(int $postId): array
    {
        $post = BoardEnv::post($postId);
        $board = $post ? BoardEnv::board((int) $post->board_id) : null;

        return [$post, $board];
    }

    private function linkByCustom(int $customId): ?object
    {
        return DB::table(LinkTable::NAME)->where('custom_comment_id', $customId)->first() ?: null;
    }

    /**
     * 공식 댓글 번호부터 위로: [{id, depth}, …]
     *
     * @return list<array{id: int, depth: int}>
     */
    private function boardChain(int $boardCommentId): array
    {
        $chain = [];
        $id = $boardCommentId;
        for ($i = 0; $i < 12 && $id > 0; $i++) {
            $c = DB::table('board_comments')->where('id', $id)->first(['id', 'parent_id', 'depth']);
            if (! $c) {
                break;
            }
            $chain[] = ['id' => (int) $c->id, 'depth' => (int) ($c->depth ?? 0)];
            $id = (int) ($c->parent_id ?? 0);
        }

        return $chain;
    }

    private function boardText(object $row): string
    {
        $body = (string) ($row->body ?? '');
        // 본문 코드에 없는 첨부 이미지(image_path 칸)도 주소로 덧붙임
        $paths = $this->decodePaths($row->image_path ?? null);
        foreach ($paths as $p) {
            if (! str_contains($body, $p)) {
                $body .= "\n[[img:".$p.']]';
            }
        }

        return ReplaceRules::degradeBody($body, static fn (string $p): string => BoardEnv::imageUrl($p), ! empty($row->chat_sticker ?? null));
    }

    /** @return list<string> */
    private function decodePaths(mixed $raw): array
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return [];
        }
        if (str_starts_with($s, '[')) {
            $d = json_decode($s, true);

            return is_array($d) ? array_values(array_filter(array_map(static fn ($v): string => ltrim((string) $v, '/'), $d))) : [];
        }

        return array_values(array_filter(array_map(static fn ($v): string => ltrim(trim($v), '/'), preg_split('/\s*[|,]\s*/', $s) ?: [])));
    }

    private function snapshot(object $row): string
    {
        $keep = ['id', 'digital_product_id', 'target_type', 'user_id', 'parent_id', 'body', 'image_path', 'sticker_path', 'chat_sticker', 'created_at', 'updated_at'];
        $out = [];
        foreach ($keep as $k) {
            if (property_exists($row, $k)) {
                $v = $row->{$k};
                $out[$k] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v;
            }
        }

        return (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $row */
    private function insertLink(array $row): void
    {
        $row['created_at'] = $this->now();
        $row['updated_at'] = $this->now();
        try {
            DB::table(LinkTable::NAME)->insert($row);
        } catch (\Throwable $e) {
            $this->warn('짝 표 기록 실패', $e);
        }
    }

    /**
     * @param  callable(BoardCommentGateway): int  $fn
     */
    private function callGateway(callable $fn): ?int
    {
        $gateway = self::$gatewayOverride ?? (function_exists('app') ? app(G7BoardCommentGateway::class) : new G7BoardCommentGateway);
        self::$busy = true;
        try {
            $r = $fn($gateway);

            return $r > 0 ? $r : null;
        } catch (\Throwable $e) {
            $this->warn('공식 댓글 처리 실패', $e);

            return null;
        } finally {
            self::$busy = false;
            BoardEnv::reset();
        }
    }

    private function hasCommentColumn(string $col): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn(self::COMMENTS_TABLE, $col);
        } catch (\Throwable) {
            return false;
        }
    }

    private function uploadDir(int $postId): string
    {
        return $this->storagePath('app/public/digital-comments/t/'.ReplaceRules::TARGET.'/'.$postId);
    }

    private function trashDir(int $postId): string
    {
        return $this->storagePath('app/plugins/custom-board_comments/trash/'.ReplaceRules::TARGET.'/'.$postId);
    }

    private function storagePath(string $rel): string
    {
        return function_exists('storage_path') ? storage_path($rel) : sys_get_temp_dir().'/'.$rel;
    }

    private function copyDir(string $from, string $to): void
    {
        try {
            if (! is_dir($from)) {
                return;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            if (! is_dir($to)) {
                @mkdir($to, 0775, true);
            }
            foreach ($it as $file) {
                $dest = rtrim($to, '/').'/'.substr($file->getPathname(), strlen(rtrim($from, '/')) + 1);
                if ($file->isDir()) {
                    if (! is_dir($dest)) {
                        @mkdir($dest, 0775, true);
                    }

                    continue;
                }
                if (! is_file($dest)) {
                    @copy($file->getPathname(), $dest);
                }
            }
        } catch (\Throwable $e) {
            $this->warn('이미지 보관 실패', $e);
        }
    }

    private function ip(): ?string
    {
        try {
            return function_exists('request') ? request()->ip() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function warn(string $msg, \Throwable $e): void
    {
        try {
            Log::warning('[custom-board_comments] '.$msg, ['error' => $e->getMessage()]);
        } catch (\Throwable) {
        }
    }
}

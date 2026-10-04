<?php

namespace Plugins\Custom\BoardComments\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Support\Facades\DB;
use Plugins\Custom\BoardComments\Services\MirrorService;
use Plugins\Custom\BoardComments\Support\LinkTable;
use Plugins\Custom\BoardComments\Support\ReplaceRules;

/**
 * 0.2.0 바꾸기 모드 짝 맞추기 훅 (모두 동기 — 같은 요청의 로그인 회원 · 권한으로 처리해야 하므로).
 *
 * custom-comments (1.0.1+ 의 고침 · 지움 훅) → 공식 댓글, 공식 댓글 지움 · 블라인드 · 복원 → custom-comments,
 * 게시글 삭제 · 복원 → custom-comments 보관 · 되살림, 비회원 이름 보이기.
 */
class MirrorListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        $a = static fn (string $method, int $priority = 20): array => ['method' => $method, 'priority' => $priority, 'type' => 'action', 'sync' => true];

        return [
            'custom-comments.target_comment.after_create' => $a('customCreated'),
            'custom-comments.target_comment.after_update' => $a('customUpdated'),
            'custom-comments.target_comment.after_delete' => $a('customDeleted'),
            'sirsoft-board.comment.after_delete' => $a('boardCommentDeleted'),
            'sirsoft-board.comment.after_blind' => $a('boardCommentBlinded'),
            'sirsoft-board.comment.after_restore' => $a('boardCommentRestored'),
            'sirsoft-board.post.after_delete' => $a('postDeleted', 30),
            'sirsoft-board.post.after_restore' => $a('postRestored', 30),
            'custom-comments.target_comments.presented' => ['method' => 'presented', 'priority' => 10, 'type' => 'filter', 'sync' => true],
        ];
    }

    public function handle(...$args): void {}

    public function customCreated(mixed $id = null, mixed $type = null, mixed $targetId = null, mixed $userId = 0, mixed $parentId = 0): void
    {
        $this->safe(fn () => (new MirrorService)->customCreated((int) $id, (string) $type, (int) $targetId, (int) $userId, (int) $parentId));
    }

    public function customUpdated(mixed $id = null, mixed $type = null, mixed $targetId = null, mixed $userId = 0): void
    {
        $this->safe(fn () => (new MirrorService)->customUpdated((int) $id, (string) $type, (int) $targetId));
    }

    public function customDeleted(mixed $ids = null, mixed $type = null, mixed $targetId = null, mixed $userId = 0): void
    {
        $list = is_array($ids) ? array_map('intval', $ids) : [(int) $ids];
        $this->safe(fn () => (new MirrorService)->customDeleted($list, (string) $type, (int) $targetId, (int) $userId));
    }

    public function boardCommentDeleted(mixed $comment = null, mixed $slug = null): void
    {
        $this->safe(fn () => (new MirrorService)->boardCommentGone($this->id($comment), (string) $slug, 'board_deleted'));
    }

    public function boardCommentBlinded(mixed $comment = null, mixed $slug = null): void
    {
        $this->safe(fn () => (new MirrorService)->boardCommentGone($this->id($comment), (string) $slug, 'board_blinded'));
    }

    public function boardCommentRestored(mixed $comment = null, mixed $slug = null): void
    {
        $this->safe(fn () => (new MirrorService)->boardCommentRestored($this->id($comment), (string) $slug));
    }

    public function postDeleted(mixed $post = null, mixed $slug = null, mixed $options = null): void
    {
        $this->safe(fn () => (new MirrorService)->postDeleted($this->id($post)));
    }

    public function postRestored(mixed $post = null, mixed $slug = null): void
    {
        $this->safe(fn () => (new MirrorService)->postRestored($this->id($post)));
    }

    /**
     * 옮겨 온 비회원 댓글(회원 번호 0)에 원래 이름을 붙입니다.
     */
    public function presented(mixed $tree = null, mixed $type = null, mixed $targetId = null): mixed
    {
        if ((string) $type !== ReplaceRules::TARGET || ! is_array($tree)) {
            return $tree;
        }
        try {
            $ids = [];
            $walk = static function (array $items) use (&$walk, &$ids): void {
                foreach ($items as $it) {
                    if (is_array($it)) {
                        if ((int) ($it['user_id'] ?? -1) === 0 && isset($it['id'])) {
                            $ids[] = (int) $it['id'];
                        }
                        if (isset($it['replies']) && is_array($it['replies'])) {
                            $walk($it['replies']);
                        }
                    }
                }
            };
            $walk($tree);
            if ($ids === [] || ! LinkTable::ensure()) {
                return $tree;
            }
            $names = DB::table(LinkTable::NAME)->whereIn('custom_comment_id', $ids)->whereNotNull('author_name')->pluck('author_name', 'custom_comment_id')->all();
            if ($names === []) {
                return $tree;
            }

            return self::applyNames($tree, $names);
        } catch (\Throwable) {
            return $tree;
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int|string, string>  $names
     * @return array<int, mixed>
     */
    public static function applyNames(array $items, array $names): array
    {
        foreach ($items as $i => $it) {
            if (! is_array($it)) {
                continue;
            }
            $id = (int) ($it['id'] ?? 0);
            if ((int) ($it['user_id'] ?? -1) === 0 && isset($names[$id]) && trim((string) $names[$id]) !== '') {
                $name = trim((string) $names[$id]);
                $it['author_name'] = $name;
                $author = is_array($it['author'] ?? null) ? $it['author'] : [];
                $author['name'] = $name;
                $author['guest'] = true;
                $it['author'] = $author;
            }
            if (isset($it['replies']) && is_array($it['replies'])) {
                $it['replies'] = self::applyNames($it['replies'], $names);
            }
            $items[$i] = $it;
        }

        return $items;
    }

    private function id(mixed $model): int
    {
        if (is_object($model)) {
            return (int) ($model->id ?? 0);
        }
        if (is_array($model)) {
            return (int) ($model['id'] ?? 0);
        }

        return (int) $model;
    }

    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            try {
                \Illuminate\Support\Facades\Log::warning('[custom-board_comments] 짝 맞추기 실패', ['error' => $e->getMessage()]);
            } catch (\Throwable) {
            }
        }
    }
}

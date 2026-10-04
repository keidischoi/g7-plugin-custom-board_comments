<?php

namespace Plugins\Custom\BoardComments\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\Custom\BoardComments\Support\BoardEnv;
use Plugins\Custom\BoardComments\Support\BoardPermission;
use Plugins\Custom\BoardComments\Support\ReplaceRules;

/**
 * custom-comments 가 'board_post' 댓글의 권한을 물을 때 공식 게시판 권한으로 답합니다 (0.2.0).
 *
 * - read: sirsoft-board.{slug}.comments.read (또는 admin.comments.read)
 * - write: sirsoft-board.{slug}.comments.write (또는 admin.comments.write), 로그인 회원만, 블라인드 글 불가
 * - moderate(남의 댓글 지우기): 사이트 관리자 · admin.comments.write · manager
 * - 바꾸기 모드 게시판이 아니거나, 댓글 사용이 꺼졌거나, 지운 글이거나, 볼 수 없는 비밀글이면 닫힘
 * - 다른 종류는 받은 값을 그대로 돌려줍니다.
 */
class CommentAccessListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'custom-comments.target.access' => ['method' => 'access', 'priority' => 10, 'type' => 'filter', 'sync' => true],
        ];
    }

    public function handle(...$args): void {}

    public function access(mixed $value = null, mixed $type = null, mixed $targetId = null, mixed $userId = 0, mixed $action = 'read', mixed $isAdmin = false): mixed
    {
        if ((string) $type !== ReplaceRules::TARGET) {
            return $value;
        }
        try {
            return self::decide((int) $targetId, (int) $userId, (string) $action, (bool) $isAdmin);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function decide(int $postId, int $userId, string $action, bool $isAdmin): bool
    {
        $post = BoardEnv::post($postId);
        if (! $post) {
            return false;
        }
        $board = BoardEnv::board((int) $post->board_id);
        if (! $board) {
            return false;
        }
        $slug = (string) $board->slug;
        $perm = static fn (string $suffix): bool => (bool) BoardPermission::can('sirsoft-board.'.$slug.'.'.$suffix);

        $ctx = [
            'action' => $action,
            'enabled' => BoardEnv::enabled(),
            'replaced' => BoardEnv::isReplacedSlug($slug),
            'post_exists' => true,
            'post_deleted' => BoardEnv::postDeleted($post),
            'post_blinded' => BoardEnv::postBlinded($post),
            'use_comment' => (bool) ($board->use_comment ?? true),
            'is_secret' => (bool) ($post->is_secret ?? false),
            'is_admin' => $isAdmin,
            'user_id' => $userId,
        ];
        // 권한 질의는 필요한 것만 (요청마다 PermissionMiddleware 를 여러 번 돌리지 않도록)
        if ($ctx['is_secret'] && ! $isAdmin) {
            $own = $userId > 0 && (int) ($post->user_id ?? 0) === $userId;
            $ctx['can_view_secret'] = $own || (bool) BoardPermission::canViewSecret($postId);
        }
        if ($action === 'moderate') {
            $ctx['can_moderate'] = $perm('admin.comments.write') || $perm('manager');
        } elseif ($action === 'write') {
            $ctx['can_write'] = $perm('comments.write') || $perm('admin.comments.write');
        } else {
            $ctx['can_read'] = $perm('comments.read') || $perm('admin.comments.read');
        }

        return ReplaceRules::access($ctx);
    }
}

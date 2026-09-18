<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\G7\Plugin\Custom\BoardComments\Services\CommentLikeService;

/**
 * 댓글이 삭제되면 이 플러그인이 가진 추천 행도 지웁니다.
 */
class CommentDeletedCleanupListener implements HookListenerInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'sirsoft-board.comment.after_delete' => [
                'method' => 'onCommentDeleted',
                'priority' => 20,
                'sync' => true,
            ],
        ];
    }

    public function handle(...$args): void
    {
        // Individual hook methods handle events.
    }

    public function onCommentDeleted(...$args): void
    {
        try {
            $comment = $args[0] ?? null;
            $commentId = is_object($comment) ? (int) ($comment->id ?? 0) : 0;
            if ($commentId <= 0) {
                return;
            }

            app(CommentLikeService::class)->forgetComment($commentId);
        } catch (\Throwable) {
            // Boot and comment-delete paths must not take the site down.
        }
    }
}

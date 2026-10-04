<?php

namespace Plugins\Custom\BoardComments\Services;

use Plugins\Custom\BoardComments\Contracts\BoardCommentGateway;
use RuntimeException;

/**
 * sirsoft-board CommentService 로 공식 댓글을 다룹니다 (게시판 모듈 파일은 건드리지 않음).
 */
final class G7BoardCommentGateway implements BoardCommentGateway
{
    private const SERVICE = 'Modules\\Sirsoft\\Board\\Services\\CommentService';

    public function create(string $slug, array $data): int
    {
        $comment = $this->service()->createComment($slug, $data);

        return (int) ($comment->id ?? 0);
    }

    public function update(string $slug, int $commentId, int $postId, string $content): void
    {
        $this->service()->updateComment($slug, $commentId, ['content' => $content], $postId);
    }

    public function delete(string $slug, int $commentId, int $postId, string $trigger): void
    {
        $this->service()->deleteComment($slug, $commentId, $trigger, $postId);
    }

    private function service(): object
    {
        if (! class_exists(self::SERVICE)) {
            throw new RuntimeException('sirsoft-board 게시판 모듈이 없습니다.');
        }

        return app(self::SERVICE);
    }
}

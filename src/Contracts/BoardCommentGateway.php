<?php

namespace Plugins\Custom\BoardComments\Contracts;

/**
 * 공식 게시판 댓글을 만들고 · 고치고 · 지우는 입구 (0.2.0 게시판 댓글에 똑같이 남기기).
 * 실제로는 sirsoft-board 의 CommentService 를 그대로 불러 댓글 수 · 알림 · 활동 기록 · 통계가 공식 경로로 처리됩니다.
 */
interface BoardCommentGateway
{
    /**
     * @param  array<string, mixed>  $data  post_id · user_id · parent_id · content · ip_address · is_secret
     * @return int 만든 공식 댓글 번호
     */
    public function create(string $slug, array $data): int;

    public function update(string $slug, int $commentId, int $postId, string $content): void;

    public function delete(string $slug, int $commentId, int $postId, string $trigger): void;
}

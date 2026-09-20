<?php

namespace Plugins\Custom\BoardComments\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\Custom\BoardComments\Services\CommentLikeService;
use Plugins\Custom\BoardComments\Support\ActorRules;
use Plugins\Custom\BoardComments\Support\LikeRules;
use RuntimeException;

class CommentLikeController
{
    public function __construct(
        private CommentLikeService $likes,
    ) {}

    public function settings(): JsonResponse
    {
        return $this->ok($this->likes->publicSettings());
    }

    public function summary(Request $request, int $postId): JsonResponse
    {
        $userId = ActorRules::fromRequest($request);
        $guestHash = LikeRules::guestHash((string) $request->ip(), (string) $request->userAgent());

        return $this->ok($this->likes->summary(
            $postId,
            is_numeric($userId) ? (int) $userId : null,
            $guestHash,
            (string) $request->query('slug', ''),
        ));
    }

    public function toggle(Request $request, int $commentId): JsonResponse
    {
        $boardId = (int) $request->input('board_id', 0);
        $postId = (int) $request->input('post_id', 0);
        if ($postId <= 0 || $commentId <= 0) {
            return $this->fail('게시글과 댓글 정보가 필요합니다.', 422);
        }

        $userId = ActorRules::fromRequest($request);
        $guestHash = LikeRules::guestHash((string) $request->ip(), (string) $request->userAgent());

        try {
            $result = $this->likes->toggle(
                $boardId,
                $postId,
                $commentId,
                is_numeric($userId) ? (int) $userId : null,
                $guestHash,
                (string) $request->ip(),
            );
        } catch (RuntimeException $exception) {
            $status = $exception->getCode() >= 400 && $exception->getCode() < 600
                ? (int) $exception->getCode()
                : 400;

            return $this->fail($exception->getMessage(), $status);
        }

        return $this->ok($result);
    }

    private function ok(mixed $data): JsonResponse
    {
        if (class_exists(ResponseHelper::class) && method_exists(ResponseHelper::class, 'success')) {
            return ResponseHelper::success('common.success', $data);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}

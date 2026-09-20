<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\G7\Plugin\Custom\BoardComments\Services\CommentMediaService;
use Plugins\G7\Plugin\Custom\BoardComments\Support\ActorRules;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CommentMediaController
{
    public function __construct(
        private CommentMediaService $media,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $userId = ActorRules::fromRequest($request);
        if ($userId === '') {
            return $this->fail('이미지를 올리려면 로그인하세요.', 401);
        }

        $file = $request->file('file') ?? $request->file('image');
        if ($file === null) {
            return $this->fail('이미지 파일이 필요합니다.', 422);
        }

        try {
            $stored = $this->media->store(
                (string) file_get_contents($file->getRealPath()),
                (string) ($file->getMimeType() ?: $file->getClientMimeType()),
                (int) $file->getSize(),
                (string) $userId,
                (int) $request->input('post_id', 0),
                (string) $file->getClientOriginalName(),
            );
        } catch (RuntimeException $exception) {
            $status = $exception->getCode() >= 400 && $exception->getCode() < 600
                ? (int) $exception->getCode()
                : 400;

            return $this->fail($exception->getMessage(), $status);
        }

        return $this->ok($stored);
    }

    public function show(int $id): BinaryFileResponse|JsonResponse
    {
        $file = $this->media->file($id);
        if ($file === null) {
            return $this->fail('이미지를 찾지 못했습니다.', 404);
        }

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'public, max-age=86400',
        ]);
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

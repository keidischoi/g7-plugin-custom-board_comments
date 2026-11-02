<?php

namespace Plugins\Custom\BoardComments\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\Custom\BoardComments\Services\CommentMediaService;
use Plugins\Custom\BoardComments\Support\ActorRules;
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
            return $this->fail(__('custom-board_comments::messages.login_image'), 401);
        }

        $file = $request->file('file') ?? $request->file('image');
        if ($file === null) {
            return $this->fail(__('custom-board_comments::messages.need_file'), 422);
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

    public function show(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $file = $this->media->file($id);
        if ($file === null) {
            return $this->fail(__('custom-board_comments::messages.not_found'), 404);
        }

        // 0.1.25 한 번 올린 사진은 바뀌지 않음 → 30일 캐시 + ETag 304
        $res = response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'public, max-age=2592000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $res->setEtag(substr(sha1($id.'|'.$file['path'].'|'.(int) @filemtime($file['path']).'|'.(int) @filesize($file['path'])), 0, 24));
        $res->isNotModified($request);

        return $res;
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

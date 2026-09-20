<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Services;

use Illuminate\Support\Carbon;
use Plugins\G7\Plugin\Custom\BoardComments\Models\CommentMedia;
use Plugins\G7\Plugin\Custom\BoardComments\Support\MediaRules;
use Plugins\G7\Plugin\Custom\BoardComments\Support\MediaTable;
use Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsRules;
use RuntimeException;

class CommentMediaService
{
    /**
     * @return array{id: int, token: string, url: string, mime: string, size: int}
     */
    public function store(string $binary, string $mime, int $size, string $userId, int $postId, ?string $originalName = null): array
    {
        if ($userId === '') {
            throw new RuntimeException('이미지를 올리려면 로그인하세요.', 401);
        }
        if (! MediaRules::isAllowedMime($mime)) {
            throw new RuntimeException('jpg, png, gif, webp만 올릴 수 있습니다.', 422);
        }
        if (! MediaRules::isAllowedSize($size)) {
            throw new RuntimeException('이미지는 2MB 이하여야 합니다.', 422);
        }
        if (! MediaTable::ensure()) {
            throw new RuntimeException('이미지 저장소를 아직 쓸 수 없습니다.', 503);
        }

        $ext = MediaRules::extensionForMime($mime) ?? 'bin';
        $diskName = bin2hex(random_bytes(16)).'.'.$ext;
        $dir = MediaTable::directory();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('이미지 폴더를 만들지 못했습니다.', 503);
        }
        if (file_put_contents($dir.'/'.$diskName, $binary) === false) {
            throw new RuntimeException('이미지를 저장하지 못했습니다.', 503);
        }

        $row = CommentMedia::query()->create([
            'user_id' => $userId,
            'post_id' => $postId,
            'disk_name' => $diskName,
            'mime' => $mime,
            'size' => $size,
            'created_at' => class_exists(Carbon::class) ? Carbon::now() : date('c'),
        ]);

        $id = (int) $row->id;

        return [
            'id' => $id,
            'token' => MediaRules::imageToken($id),
            'url' => '/api/plugins/'.SettingsRules::PLUGIN_ID.'/media/'.$id,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /**
     * @return array{path: string, mime: string}|null
     */
    public function file(int $id): ?array
    {
        if ($id <= 0 || ! MediaTable::ensure()) {
            return null;
        }

        $row = CommentMedia::query()->find($id);
        if ($row === null) {
            return null;
        }

        foreach (MediaTable::directories() as $dir) {
            $path = $dir.'/'.$row->disk_name;
            if (is_file($path)) {
                return [
                    'path' => $path,
                    'mime' => (string) $row->mime,
                ];
            }
        }

        return null;
    }
}

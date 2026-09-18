<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Support;

final class MediaRules
{
    public const MAX_BYTES = 2_097_152;

    public const MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public static function extensionForMime(string $mime): ?string
    {
        return self::MIMES[strtolower(trim($mime))] ?? null;
    }

    public static function isAllowedMime(string $mime): bool
    {
        return self::extensionForMime($mime) !== null;
    }

    public static function isAllowedSize(int $bytes): bool
    {
        return $bytes > 0 && $bytes <= self::MAX_BYTES;
    }

    public static function imageToken(int $id): string
    {
        return '[[i:'.$id.']]';
    }

    public static function stickerToken(string $id): string
    {
        $slug = strtolower(trim($id));
        if (! preg_match('/^[a-z0-9-]{1,32}$/', $slug)) {
            return '';
        }

        return '[[s:'.$slug.']]';
    }
}

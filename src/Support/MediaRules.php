<?php

namespace Plugins\Custom\BoardComments\Support;

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

    public static function safeOriginalName(?string $original, string $mime): string
    {
        $base = basename(str_replace('\\', '/', (string) $original));
        $base = preg_replace('/[^A-Za-z0-9._\-\x{ac00}-\x{d7a3} ]+/u', '', $base) ?? '';
        $base = trim($base);
        $ext = self::extensionForMime($mime) ?? 'jpg';
        $stem = pathinfo($base, PATHINFO_FILENAME);
        $stem = trim((string) $stem);
        if ($stem === '') {
            $stem = 'image';
        }
        if (function_exists('mb_substr')) {
            $stem = mb_substr($stem, 0, 60);
        } elseif (strlen($stem) > 60) {
            $stem = substr($stem, 0, 60);
        }
        return $stem.'.'.$ext;
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

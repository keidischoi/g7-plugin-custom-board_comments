<?php

namespace Plugins\Custom\BoardComments\Support;

final class SettingsRules
{
    public const PLUGIN_ID = 'custom-board_comments';

    /** GitHub 저장소 이름으로 설치된 이전 폴더 */
    public const LEGACY_PLUGIN_ID = 'g7-plugin-custom-board_comments';

    public const SORTS = ['latest', 'oldest', 'popular'];

    public const STICKER_PACKS = ['12', '24', '48', '96', '192', '384', 'full'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'allow_guest_likes' => false,
            'best_enabled' => true,
            'best_threshold' => 5,
            'best_limit' => 3,
            'default_sort' => 'latest',
            'board_slugs' => 'free',
            'style_enabled' => true,
            'stickers_enabled' => true,
            'sticker_pack' => 'full',
            'stickers_animated' => true,
            'images_enabled' => true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>
     */
    public static function normalize(?array $raw): array
    {
        $defaults = self::defaults();
        $input = is_array($raw) ? $raw : [];

        $sort = (string) ($input['default_sort'] ?? $defaults['default_sort']);
        if (! in_array($sort, self::SORTS, true)) {
            $sort = $defaults['default_sort'];
        }

        $pack = self::normalizeStickerPack($input['sticker_pack'] ?? $input['stickerPack'] ?? $defaults['sticker_pack']);

        return [
            'enabled' => self::boolish($input['enabled'] ?? $defaults['enabled']),
            'allow_guest_likes' => self::boolish($input['allow_guest_likes'] ?? $defaults['allow_guest_likes']),
            'best_enabled' => self::boolish($input['best_enabled'] ?? $defaults['best_enabled']),
            'best_threshold' => self::clampInt($input['best_threshold'] ?? $defaults['best_threshold'], 1, 999, 5),
            'best_limit' => self::clampInt($input['best_limit'] ?? $defaults['best_limit'], 1, 20, 3),
            'default_sort' => $sort,
            'board_slugs' => self::normalizeSlugString(
                array_key_exists('board_slugs', $input) ? $input['board_slugs'] : $defaults['board_slugs']
            ),
            'style_enabled' => self::boolish($input['style_enabled'] ?? $defaults['style_enabled']),
            'stickers_enabled' => self::boolish($input['stickers_enabled'] ?? $defaults['stickers_enabled']),
            'sticker_pack' => $pack,
            'stickers_animated' => self::boolish($input['stickers_animated'] ?? $defaults['stickers_animated']),
            'images_enabled' => self::boolish($input['images_enabled'] ?? $defaults['images_enabled']),
        ];
    }

    public static function normalizeStickerPack(mixed $raw): string
    {
        $text = strtolower(trim((string) $raw));
        if (preg_match('/^(?:pack_|p|size_)?(\d+)$/', $text, $match) === 1) {
            $text = $match[1];
        }
        if ($text === 'simple') {
            return '48';
        }
        if ($text === 'all') {
            return 'full';
        }
        if (in_array($text, self::STICKER_PACKS, true)) {
            return $text;
        }

        return 'full';
    }

    /**
     * G7 설정 서비스가 플러그인 설정을 여러 겹으로 감싸거나, 빈 active 목록만 줄 때 실제 값을 꺼냅니다.
     *
     * @param  list<mixed>  $chunks
     * @return array<string, mixed>|null
     */
    public static function firstSettings(array $chunks): ?array
    {
        $merged = [];
        foreach ($chunks as $chunk) {
            $extracted = self::extractSettings($chunk);
            if ($extracted === null || $extracted === []) {
                continue;
            }
            $merged = array_merge($merged, $extracted);
        }

        return $merged === [] ? null : $merged;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function extractSettings(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? self::extractSettings($decoded) : null;
        }
        if (! is_array($raw)) {
            return null;
        }
        foreach ([self::PLUGIN_ID, self::LEGACY_PLUGIN_ID] as $pluginId) {
            if (isset($raw[$pluginId])) {
                return self::extractSettings($raw[$pluginId]);
            }
        }
        foreach (['form', 'settings', 'data', 'values', 'config', 'attributes', 'payload'] as $key) {
            if (isset($raw[$key]) && is_array($raw[$key])) {
                $inner = self::extractSettings($raw[$key]);
                if ($inner !== null) {
                    return $inner;
                }
            }
        }

        return self::looksLikePluginSettings($raw) ? $raw : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function looksLikePluginSettings(array $raw): bool
    {
        foreach (['sticker_pack', 'stickerPack', 'stickers_animated', 'stickers_enabled', 'board_slugs', 'default_sort', 'allow_guest_likes', 'best_threshold'] as $key) {
            if (array_key_exists($key, $raw)) {
                return true;
            }
        }

        return array_key_exists('enabled', $raw) && count($raw) <= 20 && ! isset($raw[self::PLUGIN_ID]);
    }

    /**
     * @return list<string>
     */
    public static function parseSlugs(mixed $raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[\s,]+/', (string) $raw) ?: [];
        }

        $slugs = [];
        foreach ($parts as $part) {
            $slug = strtolower(trim((string) $part));
            if ($slug === '' || in_array($slug, $slugs, true)) {
                continue;
            }
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,80}$/', $slug)) {
                continue;
            }
            $slugs[] = $slug;
        }

        return $slugs;
    }

    public static function appliesToBoard(string $slug, mixed $rawSlugs): bool
    {
        $allowed = self::parseSlugs($rawSlugs);

        return $allowed === [] || in_array(strtolower(trim($slug)), $allowed, true);
    }

    private static function normalizeSlugString(mixed $raw): string
    {
        return implode(', ', self::parseSlugs($raw));
    }

    private static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }
        $text = strtolower(trim((string) $value));

        return ! in_array($text, ['', '0', 'false', 'off', 'no'], true);
    }

    private static function clampInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $number = (int) round((float) $value);

        return max($min, min($max, $number));
    }
}

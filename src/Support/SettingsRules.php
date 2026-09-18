<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Support;

final class SettingsRules
{
    public const PLUGIN_ID = 'g7-plugin-custom-board_comments';

    public const SORTS = ['latest', 'oldest', 'popular'];

    public const STICKER_PACKS = ['simple', 'full'];

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

        $pack = (string) ($input['sticker_pack'] ?? $defaults['sticker_pack']);
        if (! in_array($pack, self::STICKER_PACKS, true)) {
            $pack = $defaults['sticker_pack'];
        }

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

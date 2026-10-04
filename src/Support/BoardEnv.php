<?php

namespace Plugins\Custom\BoardComments\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 0.2.0 바꾸기 모드가 보는 사이트 상태 (설정 · custom-comments 켜짐 · 게시글 · 게시판). 요청 안에서만 기억합니다.
 */
final class BoardEnv
{
    public const MIN_COMMENTS_VERSION = '1.0.0';

    /** @var array<string, mixed>|null */
    private static ?array $settings = null;

    /** @var array{active: bool, version: string}|null */
    private static ?array $plugin = null;

    /** @var array<int, object|null> */
    private static array $posts = [];

    /** @var array<int, object|null> */
    private static array $boards = [];

    /** 검사용: 설정 · 플러그인 상태를 바꿔 끼움 */
    public static ?\Closure $settingsResolver = null;

    public static function reset(): void
    {
        self::$settings = null;
        self::$plugin = null;
        self::$posts = [];
        self::$boards = [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }
        if (self::$settingsResolver !== null) {
            return self::$settings = SettingsRules::normalize((self::$settingsResolver)());
        }
        try {
            if (is_file(SettingsStore::path())) {
                return self::$settings = SettingsStore::get();
            }
            if (function_exists('app') && class_exists(\Plugins\Custom\BoardComments\Services\CommentLikeService::class)) {
                return self::$settings = app(\Plugins\Custom\BoardComments\Services\CommentLikeService::class)->publicSettings();
            }
        } catch (\Throwable) {
        }

        return self::$settings = SettingsRules::defaults();
    }

    /**
     * custom-comments 1.0.0+ 가 켜져 있고 댓글 표에 target_type 칸이 있는지.
     *
     * @return array{active: bool, version: string}
     */
    public static function commentsPlugin(): array
    {
        if (self::$plugin !== null) {
            return self::$plugin;
        }
        $state = ['active' => false, 'version' => ''];
        try {
            $row = class_exists(\App\Models\Plugin::class)
                ? \App\Models\Plugin::query()->where('identifier', ReplaceRules::COMMENTS_PLUGIN)->first(['identifier', 'status', 'version'])
                : DB::table('plugins')->where('identifier', ReplaceRules::COMMENTS_PLUGIN)->first(['identifier', 'status', 'version']);
            if ($row) {
                $status = $row->status;
                if ($status instanceof \BackedEnum) {
                    $status = $status->value;
                }
                $version = (string) ($row->version ?? '');
                $dirOk = ! function_exists('base_path') || ! is_dir(base_path('plugins')) || is_dir(base_path('plugins/'.ReplaceRules::COMMENTS_PLUGIN));
                $state = [
                    'active' => strtolower((string) $status) === 'active' && $dirOk && $version !== ''
                        && version_compare($version, self::MIN_COMMENTS_VERSION, '>=')
                        && self::commentsTableReady(),
                    'version' => $version,
                ];
            }
        } catch (\Throwable) {
            $state = ['active' => false, 'version' => ''];
        }

        return self::$plugin = $state;
    }

    public static function commentsTableReady(): bool
    {
        try {
            return Schema::hasTable('digital_product_comments') && Schema::hasColumn('digital_product_comments', 'target_type');
        } catch (\Throwable) {
            return false;
        }
    }

    /** 지운 글까지 (deleted_at 있어도) */
    public static function post(int $postId): ?object
    {
        if ($postId <= 0) {
            return null;
        }
        if (array_key_exists($postId, self::$posts)) {
            return self::$posts[$postId];
        }
        try {
            $row = DB::table('board_posts')->where('id', $postId)->first(['id', 'board_id', 'user_id', 'is_secret', 'status', 'deleted_at']);
        } catch (\Throwable) {
            $row = null;
        }

        return self::$posts[$postId] = $row ?: null;
    }

    public static function forgetPost(int $postId): void
    {
        unset(self::$posts[$postId]);
    }

    public static function board(int $boardId): ?object
    {
        if ($boardId <= 0) {
            return null;
        }
        if (array_key_exists($boardId, self::$boards)) {
            return self::$boards[$boardId];
        }
        try {
            $row = DB::table('boards')->where('id', $boardId)->first();
        } catch (\Throwable) {
            $row = null;
        }

        return self::$boards[$boardId] = $row ?: null;
    }

    public static function boardBySlug(string $slug): ?object
    {
        try {
            $row = DB::table('boards')->where('slug', $slug)->first();
        } catch (\Throwable) {
            return null;
        }
        if ($row) {
            self::$boards[(int) $row->id] = $row;
        }

        return $row ?: null;
    }

    public static function enabled(): bool
    {
        return (bool) (self::settings()['enabled'] ?? true);
    }

    public static function isReplacedSlug(string $slug): bool
    {
        return self::enabled() && SettingsRules::isReplaced($slug, self::settings()['replace_slugs'] ?? '');
    }

    /** 바꾸기 모드가 실제로 켜진 게시판인지 (설정 + custom-comments 켜짐) */
    public static function replaceActive(string $slug): bool
    {
        return self::isReplacedSlug($slug) && self::commentsPlugin()['active'];
    }

    public static function mirrorActive(string $slug): bool
    {
        return self::replaceActive($slug) && (bool) (self::settings()['mirror_enabled'] ?? true);
    }

    public static function postDeleted(object $post): bool
    {
        $status = $post->status ?? 'published';
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        return ! empty($post->deleted_at) || (string) $status === 'deleted';
    }

    public static function postBlinded(object $post): bool
    {
        $status = $post->status ?? 'published';
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        return (string) $status === 'blinded';
    }

    /** custom-comments 댓글 이미지 공개 주소 (사이트 주소가 있으면 절대 주소) */
    public static function imageUrl(string $path): string
    {
        $rel = '/storage/'.ltrim($path, '/');
        try {
            if (function_exists('config')) {
                $base = rtrim((string) config('app.url', ''), '/');
                if ($base !== '' && preg_match('#^https?://#i', $base)) {
                    return $base.$rel;
                }
            }
        } catch (\Throwable) {
        }

        return $rel;
    }
}

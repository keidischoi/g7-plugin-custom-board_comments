<?php

namespace Plugins\Custom\BoardComments\Support;

final class SettingsStore
{
    public static function path(): string
    {
        $dir = storage_path('app/plugins/custom-board_comments');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir.'/settings.json';
    }

    public static function get(): array
    {
        $out = SettingsRules::defaults();
        $path = self::path();
        if (! is_file($path)) {
            return $out;
        }
        try {
            $raw = json_decode((string) file_get_contents($path), true);
            if (is_array($raw)) {
                return SettingsRules::normalize($raw);
            }
        } catch (\Throwable $e) {
        }

        return $out;
    }

    public static function put(array $data): array
    {
        $next = SettingsRules::normalize($data);
        file_put_contents(self::path(), json_encode($next, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $next;
    }
}

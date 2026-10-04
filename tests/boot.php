<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$pluginPhp = (string) file_get_contents($root.'/plugin.php');

expect('0.2.0 listeners', array_map('basename', glob($root.'/src/Listeners/*.php') ?: []), ['CommentAccessListener.php', 'LayoutSwapListener.php', 'MirrorListener.php']);
foreach (glob($root.'/src/Listeners/*.php') ?: [] as $lf) {
    $lsrc = (string) file_get_contents($lf);
    expectTrue(basename($lf).' implements HookListenerInterface', str_contains($lsrc, 'implements HookListenerInterface'));
    expectTrue(basename($lf).' catches errors', str_contains($lsrc, 'catch (\\Throwable'));
}
expectFalse('no install migrations', is_dir($root.'/database/migrations'));
expectTrue('plugin identifier is a literal', str_contains($pluginPhp, "IDENTIFIER = 'custom-board_comments'"));
expectTrue('inline config values', str_contains($pluginPhp, "'board_slugs' => 'free'"));
expectFalse('plugin.php does not import src classes', str_contains($pluginPhp, 'Plugins\\G7\\Plugin\\Custom\\BoardComments\\Support'));
expectTrue('plugin.php hook listeners are class-strings only (core checks class_exists)', (bool) preg_match('/function getHookListeners\(\): array\s*\{\s*return \[\s*CommentAccessListener::class,\s*LayoutSwapListener::class,\s*MirrorListener::class,\s*\];/', $pluginPhp));
expectTrue('install skips artisan migrate', str_contains($pluginPhp, 'function getMigrations()'));
expectTrue('like table helper exists', is_file($root.'/src/Support/LikeTable.php'));

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    $rel = substr($file->getPathname(), strlen($root) + 1);
    expectFalse("no Plugin::IDENTIFIER in {$rel}", str_contains($contents, 'Plugin::IDENTIFIER'));
    expectFalse("no BasePluginServiceProvider in {$rel}", str_contains($contents, 'BasePluginServiceProvider'));
}

expect('settings plugin id', \Plugins\Custom\BoardComments\Support\SettingsRules::PLUGIN_ID, 'custom-board_comments');
expect('legacy settings plugin id', \Plugins\Custom\BoardComments\Support\SettingsRules::LEGACY_PLUGIN_ID, 'g7-plugin-custom-board_comments');
expect('like table name', \Plugins\Custom\BoardComments\Support\LikeTable::NAME, 'custom_board_comment_likes');

finish();

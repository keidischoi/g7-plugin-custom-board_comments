<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$pluginPhp = (string) file_get_contents($root.'/plugin.php');

expectFalse('no custom service provider dir', is_dir($root.'/src/Providers'));
expectFalse('no hook listener dir', is_dir($root.'/src/Listeners'));
expectFalse('no install migrations', is_dir($root.'/database/migrations'));
expectTrue('plugin identifier is a literal', str_contains($pluginPhp, "IDENTIFIER = 'custom-board_comments'"));
expectTrue('inline config values', str_contains($pluginPhp, "'board_slugs' => 'free'"));
expectFalse('plugin.php does not import src classes', str_contains($pluginPhp, 'Plugins\\G7\\Plugin\\Custom\\BoardComments\\Support'));
expectFalse('plugin.php has no hook listeners', str_contains($pluginPhp, 'getHookListeners'));
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

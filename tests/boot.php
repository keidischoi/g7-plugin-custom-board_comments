<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$pluginPhp = (string) file_get_contents($root.'/plugin.php');
$providerDir = $root.'/src/Providers';

expectFalse('no custom service provider dir', is_dir($providerDir));
expectFalse('plugin.php does not autoload Plugin class for identifier', (bool) preg_match('/=\s*\\\\?SettingsRules::/', $pluginPhp));
expectTrue('plugin identifier is a literal', str_contains($pluginPhp, "IDENTIFIER = 'g7-plugin-custom-board_comments'"));
expectTrue('install loads settings without composer autoload', str_contains($pluginPhp, "require_once \$file"));
expectTrue('settings class exists for require_once', is_file($root.'/src/Support/SettingsRules.php'));

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

expect('settings plugin id', \Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsRules::PLUGIN_ID, 'g7-plugin-custom-board_comments');

finish();

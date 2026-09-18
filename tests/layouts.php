<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$jsonFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.json')) {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, '/node_modules/') || str_contains($path, '/dist/')) {
        continue;
    }
    $jsonFiles[] = $path;
}
sort($jsonFiles);

expectTrue('found json files', $jsonFiles !== []);

foreach ($jsonFiles as $path) {
    $decoded = json_decode((string) file_get_contents($path), true);
    $rel = substr($path, strlen($root) + 1);
    expectTrue("valid json {$rel}", is_array($decoded) && json_last_error() === JSON_ERROR_NONE);
}

$plugin = json_decode((string) file_get_contents($root.'/plugin.json'), true);
expect('identifier', $plugin['identifier'] ?? null, 'g7-plugin-custom-board_comments');
expect('g7 version', $plugin['g7_version'] ?? null, '>=7.0.0');
expectTrue('js asset', ($plugin['assets']['js']['output'] ?? '') === 'dist/js/plugin.iife.js');
expect('plugin version', $plugin['version'] ?? null, '0.1.16');
$iife = (string) file_get_contents($root.'/dist/js/plugin.iife.js');
expectTrue('iife exists', $iife !== '');
expectTrue('iife mounts overlay toolbar', str_contains($iife, 'cbc-toolbar--overlay') && str_contains($iife, 'data-cbc-boot'));
expectTrue('iife has sticker button', str_contains($iife, 'data-cbc-sticker') && str_contains($iife, '[[s:'));
expectTrue('iife has expanded sticker set', str_contains($iife, 'pleading') && str_contains($iife, 'bubbletea') && str_contains($iife, 'hearthands'));
expectTrue('iife sends bearer token', str_contains($iife, 'Authorization') && str_contains($iife, 'auth_token'));
expectTrue('iife keeps sort ids', str_contains($iife, 'data-cbc-comment-id'));
expectTrue('iife uses css sort order', str_contains($iife, 'cbc-comment-stack'));
expectTrue('iife closes stickers', str_contains($iife, 'cbc-close') && str_contains($iife, 'focusin'));
expectTrue('iife has pack sizes', str_contains($iife, '"12"') && str_contains($iife, '"96"') && str_contains($iife, '"384"') && str_contains($iife, 'cbc-stickers--animated'));
expectTrue('iife wraps sticker faces', str_contains($iife, 'cbc-sticker-face') && str_contains($iife, '0.1.16'));
$css = (string) file_get_contents($root.'/dist/css/plugin.css');
expectTrue('css has sticker motion', str_contains($css, 'cbc-stickers-animated') && str_contains($css, 'cbc-bob') && str_contains($css, 'cbc-sticker-icon') && str_contains($css, 'cbc-sticker-face'));
expectTrue('css does not shake picker tiles', ! str_contains($css, 'cbc-pick'));
expectTrue('css option overrides reduced motion', ! str_contains($css, 'prefers-reduced-motion'));

$layout = json_decode((string) file_get_contents($root.'/resources/layouts/admin/plugin_settings.json'), true);
expect('layout name', $layout['layout_name'] ?? null, 'plugin_settings');
expectTrue('has enabled toggle', str_contains((string) file_get_contents($root.'/resources/layouts/admin/plugin_settings.json'), '"name": "enabled"'));
expectTrue('has sticker pack', str_contains((string) file_get_contents($root.'/resources/layouts/admin/plugin_settings.json'), '"name": "sticker_pack"'));
expectTrue('has animated toggle', str_contains((string) file_get_contents($root.'/resources/layouts/admin/plugin_settings.json'), '"name": "stickers_animated"'));

$defaults = json_decode((string) file_get_contents($root.'/config/settings/defaults.json'), true);
expect('defaults sort', $defaults['defaults']['default_sort'] ?? null, 'latest');
expect('defaults free board', $defaults['defaults']['board_slugs'] ?? null, 'free');
expectTrue('frontend expose likes', ($defaults['frontend_schema']['allow_guest_likes']['expose'] ?? false) === true);
expect('defaults sticker pack', $defaults['defaults']['sticker_pack'] ?? null, 'full');
expectTrue('frontend expose pack', ($defaults['frontend_schema']['sticker_pack']['expose'] ?? false) === true);

finish();

<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsRules;

$defaults = SettingsRules::normalize(null);
expect('plugin id', SettingsRules::PLUGIN_ID, 'g7-plugin-custom-board_comments');
expect('enabled default', $defaults['enabled'], true);
expect('guest likes off', $defaults['allow_guest_likes'], false);
expect('sort default', $defaults['default_sort'], 'latest');
expect('free slug default', $defaults['board_slugs'], 'free');

$normalized = SettingsRules::normalize([
    'enabled' => '0',
    'allow_guest_likes' => '1',
    'best_enabled' => 'false',
    'best_threshold' => 4000,
    'best_limit' => 0,
    'default_sort' => 'nope',
    'board_slugs' => "Free, qna\nFree, ../x, ok_board",
    'style_enabled' => 'off',
]);

expect('enabled false', $normalized['enabled'], false);
expect('guest on', $normalized['allow_guest_likes'], true);
expect('best off', $normalized['best_enabled'], false);
expect('threshold clamp', $normalized['best_threshold'], 999);
expect('limit clamp', $normalized['best_limit'], 1);
expect('sort fallback', $normalized['default_sort'], 'latest');
expect('slug parse', $normalized['board_slugs'], 'free, qna, ok_board');
expect('style off', $normalized['style_enabled'], false);
expect('stickers default on', $defaults['stickers_enabled'], true);
expect('images default on', $defaults['images_enabled'], true);

expectTrue('all boards when empty', SettingsRules::appliesToBoard('free', ''));
expect('blank slugs mean all boards', SettingsRules::normalize(['board_slugs' => ''])['board_slugs'], '');
expectTrue('listed board', SettingsRules::appliesToBoard('QNA', 'free, qna'));
expectFalse('other board', SettingsRules::appliesToBoard('notice', 'free, qna'));

finish();

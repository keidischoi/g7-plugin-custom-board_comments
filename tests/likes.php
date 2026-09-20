<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Plugins\Custom\BoardComments\Support\LikeRules;

expectTrue('member actor', LikeRules::actor(12, null, false) !== null);
expect('member user id', LikeRules::actor(12, 'abc', false)['user_id'] ?? null, 12);
expect('member hash empty', LikeRules::actor(12, 'abc', false)['guest_hash'] ?? null, '');

expectTrue('guest blocked', LikeRules::actor(null, 'abc', false) === null);
expectTrue('guest allowed', LikeRules::actor(0, 'abc', true) !== null);
expect('guest user 0', LikeRules::actor(null, 'abc', true)['user_id'] ?? -1, 0);

$hash = LikeRules::guestHash('127.0.0.1', 'UA');
expectTrue('hash length', strlen($hash) === 64);
expect('empty ip', LikeRules::guestHash(''), '');

expect('like on', LikeRules::toggle(false), ['liked' => true, 'delta' => 1]);
expect('like off', LikeRules::toggle(true), ['liked' => false, 'delta' => -1]);

finish();

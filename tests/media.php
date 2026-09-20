<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Plugins\Custom\BoardComments\Support\MediaRules;
use Plugins\Custom\BoardComments\Support\MediaTable;

expectTrue('jpeg ok', MediaRules::isAllowedMime('image/jpeg'));
expectTrue('png ok', MediaRules::isAllowedMime('image/png'));
expectFalse('pdf blocked', MediaRules::isAllowedMime('application/pdf'));
expectTrue('2mb ok', MediaRules::isAllowedSize(2_097_152));
expectFalse('too big', MediaRules::isAllowedSize(2_097_153));
expect('image token', MediaRules::imageToken(12), '[[i:12]]');
expect('sticker token', MediaRules::stickerToken('love'), '[[s:love]]');
expect('bad sticker', MediaRules::stickerToken('../x'), '');
expect('media table name', MediaTable::NAME, 'custom_board_comment_media');

finish();

<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Plugins\Custom\BoardComments\Support\CommentEnhanceRules;

$comments = [
    ['id' => 1, 'parent_id' => null, 'depth' => 0, 'created_at' => '2026-01-02 00:00:00'],
    ['id' => 2, 'parent_id' => 1, 'depth' => 1, 'created_at' => '2026-01-03 00:00:00'],
    ['id' => 3, 'parent_id' => null, 'depth' => 0, 'created_at' => '2026-01-01 00:00:00'],
];

$counts = [1 => 2, 2 => 9, 3 => 8];

$oldest = CommentEnhanceRules::sortTree($comments, 'oldest', $counts);
expect('oldest root first', array_column($oldest, 'id'), [3, 1, 2]);

$latest = CommentEnhanceRules::sortTree($comments, 'latest', $counts);
expect('latest root first', array_column($latest, 'id'), [1, 2, 3]);

$popular = CommentEnhanceRules::sortTree($comments, 'popular', $counts);
expect('popular keeps child under parent', array_column($popular, 'id'), [3, 1, 2]);

$best = CommentEnhanceRules::bestIds($counts, 5, 2);
expect('best ids', $best, [2, 3]);

$pinned = CommentEnhanceRules::pinBest($popular, [3]);
expect('pin best root', array_column($pinned, 'id'), [3, 1, 2]);

$visible = CommentEnhanceRules::visibleComments($comments, []);
expect('collapsed hides reply', array_column($visible, 'id'), [1, 3]);

$expanded = CommentEnhanceRules::visibleComments($comments, [1]);
expect('expanded shows reply', array_column($expanded, 'id'), [1, 2, 3]);

finish();

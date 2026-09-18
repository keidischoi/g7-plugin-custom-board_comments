<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$routes = (string) file_get_contents($root.'/src/routes/api.php');
$pluginPhp = (string) file_get_contents($root.'/plugin.php');
$migration = (string) file_get_contents($root.'/database/migrations/2026_09_18_000001_create_custom_board_comment_likes_table.php');

expectTrue('settings route', str_contains($routes, "Route::get('/settings'"));
expectTrue('summary route', str_contains($routes, "Route::get('/posts/{postId}/likes'"));
expectTrue('like route', str_contains($routes, "Route::post('/comments/{commentId}/like'"));
expectTrue('dynamic table', str_contains($pluginPhp, 'custom_board_comment_likes'));
expectTrue('delete hook listener', str_contains($pluginPhp, 'CommentDeletedCleanupListener'));
expectTrue('unique actor', str_contains($migration, 'cbc_likes_actor_unique'));
expectTrue('composer namespace', str_contains((string) file_get_contents($root.'/composer.json'), 'Plugins\\\\G7\\\\Plugin\\\\Custom\\\\BoardComments\\\\'));

finish();

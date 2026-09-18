<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$routes = (string) file_get_contents($root.'/src/routes/api.php');
$pluginPhp = (string) file_get_contents($root.'/plugin.php');
$likeTable = (string) file_get_contents($root.'/src/Support/LikeTable.php');
$pluginJson = json_decode((string) file_get_contents($root.'/plugin.json'), true);

expectTrue('settings route', str_contains($routes, "Route::get('/settings'"));
expectTrue('summary route', str_contains($routes, "Route::get('/posts/{postId}/likes'"));
expectTrue('like route', str_contains($routes, "Route::post('/comments/{commentId}/like'"));
expectTrue('media upload route', str_contains($routes, "Route::post('/media'"));
expectTrue('media show route', str_contains($routes, "Route::get('/media/{id}'"));
expectTrue('dynamic table', str_contains($pluginPhp, 'custom_board_comment_likes'));
expectTrue('media dynamic table', str_contains($pluginPhp, 'custom_board_comment_media'));
expectFalse('no hook listeners', str_contains($pluginPhp, 'CommentDeletedCleanupListener'));
expectFalse('no custom service provider', is_dir($root.'/src/Providers'));
expectTrue('unique actor', str_contains($likeTable, 'cbc_likes_actor_unique'));
expectTrue('lazy create table', str_contains($likeTable, 'Schema::create'));
expect('no hard module dependency', $pluginJson['dependencies']['modules'] ?? ['x'], []);
expectTrue('composer namespace', str_contains((string) file_get_contents($root.'/composer.json'), 'Plugins\\\\G7\\\\Plugin\\\\Custom\\\\BoardComments\\\\'));

finish();

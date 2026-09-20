<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$routes = (string) file_get_contents($root.'/src/routes/api.php');
$pluginPhp = (string) file_get_contents($root.'/plugin.php');
$likeTable = (string) file_get_contents($root.'/src/Support/LikeTable.php');
$mediaTable = (string) file_get_contents($root.'/src/Support/MediaTable.php');
$pluginJson = json_decode((string) file_get_contents($root.'/plugin.json'), true);

expectTrue('settings route', str_contains($routes, "Route::get('/settings'"));
expectTrue('summary route', str_contains($routes, "Route::get('/posts/{postId}/likes'"));
expectTrue('like route', str_contains($routes, "Route::post('/comments/{commentId}/like'"));
expectTrue('media upload route', str_contains($routes, "Route::post('/media'"));
expectTrue('media show route', str_contains($routes, "Route::get('/media/{id}'"));
expectTrue('media uses optional sanctum', str_contains($routes, "optional.sanctum"));
expectTrue('like uses optional sanctum', substr_count($routes, 'optional.sanctum') >= 3);
expectTrue('dynamic table', str_contains($likeTable, 'custom_board_comment_likes'));
expectTrue('media dynamic table', str_contains($mediaTable, 'custom_board_comment_media'));
expectFalse('no hook listeners', str_contains($pluginPhp, 'CommentDeletedCleanupListener'));
expectFalse('no custom service provider', is_dir($root.'/src/Providers'));
expectTrue('unique actor', str_contains($likeTable, 'cbc_likes_actor_unique'));
expectTrue('actor rules', is_file($root.'/src/Support/ActorRules.php'));
expect('no hard module dependency', $pluginJson['dependencies']['modules'] ?? ['x'], []);
expectTrue('composer namespace', str_contains((string) file_get_contents($root.'/composer.json'), 'Plugins\\\\Custom\\\\BoardComments\\\\'));

finish();

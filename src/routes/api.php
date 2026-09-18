<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Plugin\Custom\BoardComments\Http\Controllers\CommentLikeController;

/*
|--------------------------------------------------------------------------
| Custom Board Comments API
|--------------------------------------------------------------------------
|
| PluginRouteServiceProvider prefix:
| - URL: api/plugins/g7-plugin-custom-board_comments
| - Name: api.plugins.g7-plugin-custom-board_comments.
|
*/

Route::get('/settings', [CommentLikeController::class, 'settings'])
    ->name('settings');

Route::get('/posts/{postId}/likes', [CommentLikeController::class, 'summary'])
    ->whereNumber('postId')
    ->name('posts.likes');

Route::post('/comments/{commentId}/like', [CommentLikeController::class, 'toggle'])
    ->whereNumber('commentId')
    ->name('comments.like');

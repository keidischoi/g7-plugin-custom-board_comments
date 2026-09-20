<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Plugin\Custom\BoardComments\Http\Controllers\CommentLikeController;
use Plugins\G7\Plugin\Custom\BoardComments\Http\Controllers\CommentMediaController;
use Plugins\G7\Plugin\Custom\BoardComments\Http\Controllers\SettingsController;

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

Route::get('/admin/settings', [SettingsController::class, 'show'])->name('admin.settings.show');
Route::match(['put', 'post'], '/admin/settings', [SettingsController::class, 'save'])->name('admin.settings.save');

Route::get('/settings', [CommentLikeController::class, 'settings'])
    ->name('settings');

Route::get('/posts/{postId}/likes', [CommentLikeController::class, 'summary'])
    ->middleware('optional.sanctum')
    ->whereNumber('postId')
    ->name('posts.likes');

Route::post('/comments/{commentId}/like', [CommentLikeController::class, 'toggle'])
    ->middleware('optional.sanctum')
    ->whereNumber('commentId')
    ->name('comments.like');

Route::post('/media', [CommentMediaController::class, 'store'])
    ->middleware('optional.sanctum')
    ->name('media.store');

Route::get('/media/{id}', [CommentMediaController::class, 'show'])
    ->whereNumber('id')
    ->name('media.show');

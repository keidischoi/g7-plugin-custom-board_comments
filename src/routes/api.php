<?php

use Illuminate\Support\Facades\Route;
use Plugins\Custom\BoardComments\Http\Controllers\CommentLikeController;
use Plugins\Custom\BoardComments\Http\Controllers\CommentMediaController;
use Plugins\Custom\BoardComments\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Custom Board Comments API
|--------------------------------------------------------------------------
|
| PluginRouteServiceProvider prefix:
| - URL: api/plugins/custom-board_comments
| - Name: api.plugins.custom-board_comments.
|
*/

// 관리자 설정 — 코어 플러그인 설정 API 와 같은 권한 (조회: core.plugins.read / 저장: core.plugins.update)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/admin/settings', [SettingsController::class, 'show'])
        ->middleware('permission:admin,core.plugins.read')
        ->name('admin.settings.show');
    Route::match(['put', 'post'], '/admin/settings', [SettingsController::class, 'save'])
        ->middleware('permission:admin,core.plugins.update')
        ->name('admin.settings.save');
});

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

<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class LikeTable
{
    public const NAME = 'custom_board_comment_likes';

    public static function ensure(): bool
    {
        try {
            if (Schema::hasTable(self::NAME)) {
                return true;
            }

            if (! class_exists(Schema::class) || ! class_exists(Blueprint::class)) {
                return false;
            }

            Schema::create(self::NAME, static function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('board_id');
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('comment_id');
                $table->unsignedBigInteger('user_id')->default(0);
                $table->string('guest_hash', 64)->default('');
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['comment_id', 'user_id', 'guest_hash'], 'cbc_likes_actor_unique');
                $table->index(['post_id', 'comment_id'], 'cbc_likes_post_comment');
                $table->index('board_id', 'cbc_likes_board');
            });

            return Schema::hasTable(self::NAME);
        } catch (\Throwable) {
            return false;
        }
    }
}

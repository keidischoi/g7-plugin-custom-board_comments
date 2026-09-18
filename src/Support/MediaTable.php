<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class MediaTable
{
    public const NAME = 'custom_board_comment_media';

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
                $table->string('user_id', 64)->default('');
                $table->unsignedBigInteger('post_id')->default(0);
                $table->string('disk_name', 80);
                $table->string('mime', 40);
                $table->unsignedInteger('size')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->index('post_id', 'cbc_media_post');
            });

            return Schema::hasTable(self::NAME);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function directory(): string
    {
        if (function_exists('storage_path')) {
            return storage_path('app/g7-plugin-custom-board_comments/media');
        }

        return sys_get_temp_dir().'/g7-plugin-custom-board_comments/media';
    }
}

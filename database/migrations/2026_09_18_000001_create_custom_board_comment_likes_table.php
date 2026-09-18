<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_board_comment_likes')) {
            return;
        }

        Schema::create('custom_board_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id')->comment('게시판 ID');
            $table->unsignedBigInteger('post_id')->comment('게시글 ID');
            $table->unsignedBigInteger('comment_id')->comment('댓글 ID');
            $table->unsignedBigInteger('user_id')->default(0)->comment('회원 ID, 비회원은 0');
            $table->string('guest_hash', 64)->default('')->comment('비회원 추천 키');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['comment_id', 'user_id', 'guest_hash'], 'cbc_likes_actor_unique');
            $table->index(['post_id', 'comment_id'], 'cbc_likes_post_comment');
            $table->index('board_id', 'cbc_likes_board');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_board_comment_likes');
    }
};

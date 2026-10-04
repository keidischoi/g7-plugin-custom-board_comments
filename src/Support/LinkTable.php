<?php

namespace Plugins\Custom\BoardComments\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 0.2.0 바꾸기 모드 짝 표: custom-comments 댓글 번호 ↔ 공식 게시판 댓글 번호.
 *
 * - origin: mirror(새 댓글을 공식 댓글에 남김) · migrate(옮기기 명령) · snapshot(글 삭제 때 보관만)
 * - state: active · custom_deleted(이쪽에서 지움 → 공식 댓글도 지움) · board_deleted / board_blinded(공식 쪽에서 지움 · 블라인드)
 *          · purged(글이 지워져 custom-comments 쪽을 비움 — 글 복원 때 snapshot 으로 되살림)
 * - snapshot: custom-comments 댓글 그대로(JSON) — 되살릴 때 씀
 * - author_name: 옮겨 온 비회원 댓글 이름 (custom-comments 는 회원 번호만 앎)
 *
 * 처음 쓸 때 만듭니다 (설치 때 migrate 를 돌리지 않는 이 플러그인 방식 그대로). 플러그인을 지워도 남깁니다.
 */
final class LinkTable
{
    public const NAME = 'custom_board_comment_links';

    private static ?bool $ready = null;

    public static function ensure(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            if (Schema::hasTable(self::NAME)) {
                return self::$ready = true;
            }
            Schema::create(self::NAME, static function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('board_id')->default(0);
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('custom_comment_id')->nullable();
                $table->unsignedBigInteger('board_comment_id')->nullable();
                $table->string('origin', 16)->default('mirror');
                $table->string('state', 16)->default('active');
                $table->string('author_name', 50)->nullable();
                $table->longText('snapshot')->nullable();
                $table->timestamps();
                $table->unique('custom_comment_id', 'cbc_links_custom_unique');
                $table->unique('board_comment_id', 'cbc_links_board_unique');
                $table->index(['post_id', 'state'], 'cbc_links_post_state');
            });

            return self::$ready = Schema::hasTable(self::NAME);
        } catch (\Throwable) {
            return self::$ready = false;
        }
    }

    public static function forget(): void
    {
        self::$ready = null;
    }
}

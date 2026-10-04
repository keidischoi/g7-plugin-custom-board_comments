<?php

namespace Plugins\Custom\BoardComments\Support;

/**
 * 0.2.0 바꾸기 모드의 순수 규칙 (Laravel 없이 검사 가능).
 *
 * - 댓글 대상 종류: board_post (대상 번호 = 게시글 번호. 공식 게시판은 board_posts 한 표라 번호가 겹치지 않음)
 * - 권한 판정: custom-comments 가 필터 custom-comments.target.access 로 물을 때 돌려줄 값
 * - 본문 낮추기: custom-comments 본문([[sticker:]] [[img:]] 코드) → 공식 게시판 댓글용 글
 */
final class ReplaceRules
{
    public const TARGET = 'board_post';

    public const COMMENTS_PLUGIN = 'custom-comments';

    /** 1.0.0 전 식별자 (0.8.0+ 이면 대상 종류를 압니다) */
    public const LEGACY_COMMENTS_PLUGIN = 'custom-digital_comments';

    public const STICKER_TEXT = '[스티커]';

    public const IMAGE_TEXT = '[이미지]';

    /**
     * @param  array{
     *     action?: string,
     *     enabled?: bool,
     *     replaced?: bool,
     *     post_exists?: bool,
     *     post_deleted?: bool,
     *     post_blinded?: bool,
     *     use_comment?: bool,
     *     is_secret?: bool,
     *     can_view_secret?: bool,
     *     is_admin?: bool,
     *     can_read?: bool,
     *     can_write?: bool,
     *     can_moderate?: bool,
     *     user_id?: int
     * }  $c
     */
    public static function access(array $c): bool
    {
        $action = (string) ($c['action'] ?? 'read');
        if (! ($c['post_exists'] ?? false) || ($c['post_deleted'] ?? false)) {
            return false;
        }
        // 바꾸기 모드 게시판에서만 이 댓글을 엽니다 (다른 게시판 글 번호로 API 를 직접 불러도 닫힘)
        if (! ($c['enabled'] ?? false) || ! ($c['replaced'] ?? false)) {
            return false;
        }
        // 게시판 「댓글 사용」 이 꺼져 있으면 읽기 · 쓰기 모두 닫힘 (공식 댓글 칸도 안 보이는 것과 같게)
        if (! ($c['use_comment'] ?? false)) {
            return false;
        }
        $admin = (bool) ($c['is_admin'] ?? false);
        // 비밀글: 공식 게시판의 비밀글 판정(작성자 · 비밀글 읽기 권한 · 관리자 · 비밀번호 확인)을 통과해야 함
        if (($c['is_secret'] ?? false) && ! ($c['can_view_secret'] ?? false) && ! $admin) {
            return false;
        }
        if ($action === 'moderate') {
            return $admin || (bool) ($c['can_moderate'] ?? false);
        }
        if ($action === 'write') {
            // 블라인드 글에는 공식 게시판처럼 새 댓글을 못 씀 (관리자도)
            if ($c['post_blinded'] ?? false) {
                return false;
            }
            if ((int) ($c['user_id'] ?? 0) <= 0) {
                return false;   // custom-comments 는 로그인 회원만 씁니다 → 401 「로그인이 필요합니다」
            }

            return $admin || (bool) ($c['can_write'] ?? false);
        }

        return $admin || (bool) ($c['can_read'] ?? false);
    }

    /**
     * custom-comments 본문 → 공식 게시판 댓글 글.
     * 스티커 코드는 [스티커], 이미지 코드는 「[이미지] 주소」, 러블리 채팅 스티커는 끝에 [스티커].
     *
     * @param  callable(string): string  $imageUrl  저장 경로 → 공개 주소
     */
    public static function degradeBody(string $body, ?callable $imageUrl = null, bool $chatSticker = false): string
    {
        $imageUrl ??= static fn (string $path): string => '/storage/'.ltrim($path, '/');
        $out = preg_replace_callback('/\[\[(sticker|img|s|i):([^\]]+)\]\]/i', static function (array $m) use ($imageUrl): string {
            $kind = strtolower($m[1]);
            if ($kind === 'img' || $kind === 'i') {
                $path = trim($m[2]);
                if (preg_match('#^https?://#i', $path)) {
                    return self::IMAGE_TEXT.' '.$path;
                }

                return self::IMAGE_TEXT.' '.$imageUrl(ltrim(str_replace('\\', '/', $path), '/'));
            }

            return self::STICKER_TEXT;
        }, $body) ?? $body;
        $out = trim($out);
        if ($chatSticker) {
            $out = trim($out.' '.self::STICKER_TEXT);
        }

        return $out === '' ? self::STICKER_TEXT : $out;
    }

    /**
     * 공식 게시판 댓글 글 → custom-comments 본문 (옮기기 명령). 글 그대로, 앞뒤 공백만 정리.
     * 공식 댓글이 HTML 이면 태그를 걷어 글만 남깁니다 (custom-comments 는 글 + 코드만 보여 줌).
     */
    public static function boardTextToBody(string $content): string
    {
        $text = $content;
        if ($text !== strip_tags($text)) {
            $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
            $text = preg_replace('#</p>\s*<p[^>]*>#i', "\n", $text) ?? $text;
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return trim($text);
    }

    /**
     * 공식 게시판의 답글 깊이 한도에 맞춰 붙일 부모를 고릅니다.
     * $chain = [부모, 할아버지, …] 공식 댓글 번호와 그 깊이. 한도를 넘으면 위로 올라가고, 끝내 안 되면 0(최상위).
     *
     * @param  list<array{id: int, depth: int}>  $chain
     */
    public static function pickBoardParent(array $chain, int $maxDepth): int
    {
        foreach ($chain as $node) {
            if ((int) $node['depth'] + 1 <= $maxDepth) {
                return (int) $node['id'];
            }
        }

        return 0;
    }
}

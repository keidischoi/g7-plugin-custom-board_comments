<?php

namespace Plugins\G7\Plugin\Custom\BoardComments;

use App\Extension\AbstractPlugin;
use Plugins\G7\Plugin\Custom\BoardComments\Listeners\CommentDeletedCleanupListener;
use Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsRules;

/**
 * 공식 게시판 댓글에 추천·베스트·정렬을 더하는 플러그인입니다.
 */
class Plugin extends AbstractPlugin
{
    public const IDENTIFIER = 'g7-plugin-custom-board_comments';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getSettingsSchema(): array
    {
        return [
            'enabled' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '댓글 확장 사용', 'en' => 'Enable comment extras'],
                'hint' => [
                    'ko' => '게시글 상세의 댓글에 추천·베스트·정렬을 표시합니다.',
                    'en' => 'Shows likes, best comments, and sorting on post comment sections.',
                ],
                'required' => false,
            ],
            'allow_guest_likes' => [
                'type' => 'boolean',
                'default' => false,
                'label' => ['ko' => '비회원 추천 허용', 'en' => 'Allow guest likes'],
                'hint' => [
                    'ko' => '끄면 로그인한 회원만 추천할 수 있습니다. 비회원은 IP 기준으로 1회만 추천합니다.',
                    'en' => 'When off, only signed-in members can like. Guests are limited to one like per IP.',
                ],
                'required' => false,
            ],
            'best_enabled' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '베스트 댓글 사용', 'en' => 'Enable best comments'],
                'hint' => [
                    'ko' => '추천 수가 기준 이상인 댓글을 상단에 고정하고 배지를 붙입니다.',
                    'en' => 'Pins comments at or above the like threshold and shows a badge.',
                ],
                'required' => false,
            ],
            'best_threshold' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 999,
                'default' => 5,
                'label' => ['ko' => '베스트 기준 추천 수', 'en' => 'Best-comment like threshold'],
                'required' => true,
            ],
            'best_limit' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 20,
                'default' => 3,
                'label' => ['ko' => '베스트 댓글 최대 개수', 'en' => 'Maximum best comments'],
                'required' => true,
            ],
            'default_sort' => [
                'type' => 'enum',
                'options' => ['latest', 'oldest', 'popular'],
                'default' => 'latest',
                'label' => ['ko' => '기본 정렬', 'en' => 'Default sort'],
                'hint' => [
                    'ko' => '최신순, 등록순, 추천순 중 방문자 화면의 기본값입니다.',
                    'en' => 'Default visitor sort: latest, oldest, or most liked.',
                ],
                'required' => true,
            ],
            'board_slugs' => [
                'type' => 'string',
                'default' => '',
                'label' => ['ko' => '적용 게시판 슬러그', 'en' => 'Board slugs'],
                'hint' => [
                    'ko' => '쉼표 또는 줄바꿈으로 구분합니다. 비우면 모든 게시판에 적용합니다.',
                    'en' => 'Comma or newline separated. Leave empty to apply to every board.',
                ],
                'required' => false,
            ],
            'style_enabled' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '댓글 카드 스타일', 'en' => 'Comment card styling'],
                'hint' => [
                    'ko' => '추천 버튼과 베스트 배지 스타일을 적용합니다. 테마 파일은 수정하지 않습니다.',
                    'en' => 'Applies like-button and best-badge styles without editing the theme.',
                ],
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigValues(): array
    {
        return SettingsRules::defaults();
    }

    /**
     * @return array<class-string>
     */
    public function getHookListeners(): array
    {
        return [
            CommentDeletedCleanupListener::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getDynamicTables(): array
    {
        return [
            'custom_board_comment_likes',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'keidischoi',
            'license' => 'MIT',
            'keywords' => ['board', 'comments', 'likes', 'best'],
        ];
    }
}

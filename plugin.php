<?php

namespace Plugins\G7\Plugin\Custom\BoardComments;

use App\Extension\AbstractPlugin;

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
                'default' => 'free',
                'label' => ['ko' => '적용 게시판 슬러그', 'en' => 'Board slugs'],
                'hint' => [
                    'ko' => '기본은 자유게시판(free)입니다. 쉼표로 더 넣을 수 있고, 비우면 모든 게시판에 적용합니다.',
                    'en' => 'Defaults to the free board. Comma-separated; leave empty to apply to every board.',
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
            'stickers_enabled' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '스티커 삽입', 'en' => 'Sticker insert'],
                'hint' => [
                    'ko' => '댓글 입력창에 스티커를 넣을 수 있습니다.',
                    'en' => 'Lets visitors insert stickers into the comment box.',
                ],
                'required' => false,
            ],
            'sticker_pack' => [
                'type' => 'enum',
                'options' => ['12', '24', '48', '96', '192', '384', 'full'],
                'default' => 'full',
                'label' => ['ko' => '스티커 세트', 'en' => 'Sticker pack'],
                'hint' => [
                    'ko' => '12, 24, 48, 96처럼 배수로 고릅니다. 전체는 700개 넘습니다.',
                    'en' => 'Choose 12, 24, 48, 96, and so on. Full is 700+ stickers.',
                ],
                'required' => true,
            ],
            'stickers_animated' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '움직이는 스티커', 'en' => 'Animated stickers'],
                'hint' => [
                    'ko' => '스티커 아이콘만 위아래로 뛰거나 흔들립니다. GIF를 받지 않고 CSS로만 움직입니다. 끄면 가만히 있는 이모지입니다.',
                    'en' => 'Only sticker icons bounce and wiggle with CSS. Turn off for still emoji. No remote GIF files.',
                ],
                'required' => false,
            ],
            'images_enabled' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '이미지 삽입', 'en' => 'Image insert'],
                'hint' => [
                    'ko' => '로그인한 회원이 댓글에 이미지를 올릴 수 있습니다. 최대 2MB.',
                    'en' => 'Signed-in members can attach images to comments. 2MB max.',
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
        return [
            'enabled' => true,
            'allow_guest_likes' => false,
            'best_enabled' => true,
            'best_threshold' => 5,
            'best_limit' => 3,
            'default_sort' => 'latest',
            'board_slugs' => 'free',
            'style_enabled' => true,
            'stickers_enabled' => true,
            'sticker_pack' => 'full',
            'stickers_animated' => true,
            'images_enabled' => true,
        ];
    }

    /**
     * 설치 때 artisan migrate를 돌리지 않습니다. 추천 테이블은 처음 쓸 때 만듭니다.
     *
     * @return array<int, string>
     */
    public function getMigrations(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function getDynamicTables(): array
    {
        // Keep likes/media tables and files after uninstall.
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'keidischoi',
            'license' => 'MIT',
            'keywords' => ['board', 'comments', 'likes', 'best', 'sticker', 'image'],
        ];
    }
}

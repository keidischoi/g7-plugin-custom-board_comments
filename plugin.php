<?php

namespace Plugins\Custom\BoardComments;

use App\Extension\AbstractPlugin;
use Plugins\Custom\BoardComments\Listeners\CommentAccessListener;
use Plugins\Custom\BoardComments\Listeners\LayoutSwapListener;
use Plugins\Custom\BoardComments\Listeners\MirrorListener;

/**
 * 공식 게시판 댓글에 추천·베스트·정렬을 더하는 플러그인입니다.
 */
class Plugin extends AbstractPlugin
{
    public const IDENTIFIER = 'custom-board_comments';

    /**
     * 0.2.0 바꾸기 모드 (custom-comments 댓글로 공식 댓글 바꾸기 + 공식 댓글에 똑같이 남기기).
     * 바꾸기 게시판이 비어 있으면(기본) 아무 일도 하지 않습니다.
     *
     * @return array<int, class-string>
     */
    public function getHookListeners(): array
    {
        return [
            CommentAccessListener::class,
            LayoutSwapListener::class,
            MirrorListener::class,
        ];
    }

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
            'replace_slugs' => [
                'type' => 'string',
                'default' => '',
                'label' => ['ko' => '바꾸기 게시판 (custom-comments 댓글 쓰기)', 'en' => 'Replace boards (use custom-comments)'],
                'hint' => [
                    'ko' => '이 게시판들은 공식 댓글 칸 대신 custom-comments 댓글(이모지 · 스티커 · 이미지 · 좋아요)을 보입니다. 쉼표로 여러 개, * 는 모든 게시판, 비우면 끔. custom-comments 1.0.0+ 가 켜져 있어야 합니다.',
                    'en' => 'These boards show custom-comments instead of the built-in comment section. Comma-separated, * for all, empty = off. Requires custom-comments 1.0.0+.',
                ],
                'required' => false,
            ],
            'mirror_enabled' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '게시판 댓글에 똑같이 남기기', 'en' => 'Mirror to built-in comments'],
                'hint' => [
                    'ko' => '바꾸기 게시판의 새 댓글 · 고침 · 지움을 공식 게시판 댓글에도 남깁니다 (댓글 수 · 알림 · 관리 · 검색 · 플러그인을 꺼도 남음). 끄면 더 남기지 않고, 이미 남긴 댓글은 그대로 둡니다.',
                    'en' => 'Also writes new/edited/deleted comments on replace boards to the built-in board comments. When off, nothing is mirrored and existing mirrored rows stay.',
                ],
                'required' => false,
            ],
            'toolbar_collapsed' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '입력 도구 접어 두기', 'en' => 'Collapse comment toolbar'],
                'hint' => [
                    'ko' => '댓글 위에 떠 있는 정렬·스티커·이미지 막대를 평소에는 스티커만 보이게 접어 둡니다. 마우스를 올리거나 탭하면 펼쳐집니다.',
                    'en' => 'Keeps the floating sort/sticker/image bar collapsed to the sticker button until hovered, focused, or tapped.',
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
            'toolbar_collapsed' => true,
            'replace_slugs' => '',
            'mirror_enabled' => true,
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
            'keywords' => ['board', 'comments', 'likes', 'best', 'sticker', 'image', 'custom-comments', 'mirror'],
        ];
    }
}

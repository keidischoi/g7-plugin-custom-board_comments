<?php

namespace Plugins\Custom\BoardComments\Support;

/**
 * 0.2.0 바꾸기 모드: 공식 게시판 게시글 상세 레이아웃(board/show)의 댓글 칸 옆에
 * custom-comments 상자를 더하고, 어느 쪽을 보일지는 화면에서 고릅니다 (순수 함수, Laravel 없이 검사 가능).
 *
 * - 바꾸기 여부는 레이아웃에 굽지 않습니다. 데이터 소스 cbc_board_comments
 *   (/api/plugins/custom-board_comments/posts/{id}/mode) 의 replace 값으로 화면에서 고르므로,
 *   설정을 바꿔도 레이아웃 캐시를 지울 필요가 없습니다.
 * - 데이터 소스가 실패하면 fallback replace=false → 공식 댓글 그대로 (안전한 쪽).
 * - 못 찾으면(테마 구조가 다름) 레이아웃을 그대로 돌려줍니다.
 * - 편집기 응답(__editor 있음)은 건드리지 않습니다 (편집기가 바뀐 트리를 저장하지 않도록).
 */
final class LayoutSwap
{
    public const DATA_SOURCE = 'cbc_board_comments';

    public const MOUNT_ID = 'cbc_cc_board_comments';

    public const REPLACE_EXPR = "cbc_board_comments?.data?.replace === true";

    /** 공식 댓글 칸: 「댓글 사용 && 댓글 읽기 권한」 조건. 앞에 ! 가 붙은 것(권한 없음 안내)은 제외 */
    private const SECTION_IF = '/(?<![!\w])post\??\.data\??\.abilities\??\.can_read_comments/';

    private const COUNT_TEXT = '/^\s*\{\{\s*post\??\.data\??\.comment_count\s*\}\}\s*$/';

    /**
     * @param  array<string, mixed>  $layout
     * @return array{0: array<string, mixed>, 1: string} [레이아웃, 결과: swapped | skip:<이유>]
     */
    public static function apply(array $layout): array
    {
        if (array_key_exists('__editor', $layout)) {
            return [$layout, 'skip:editor'];
        }
        if (! self::isBoardShow($layout)) {
            return [$layout, 'skip:not-board-show'];
        }
        if (! isset($layout['components']) || ! is_array($layout['components'])) {
            return [$layout, 'skip:no-components'];
        }
        if (self::containsId($layout['components'], self::MOUNT_ID)) {
            return [$layout, 'skip:already'];
        }

        $components = $layout['components'];
        $counter = 0;
        $components = self::swapSections($components, $counter);
        if ($counter === 0) {
            return [$layout, 'skip:no-comment-section'];
        }
        $components = self::swapCountText($components);
        $layout['components'] = $components;

        if (! isset($layout['data_sources']) || ! is_array($layout['data_sources'])) {
            $layout['data_sources'] = [];
        }
        $layout['data_sources'][] = self::dataSource();

        return [$layout, 'swapped:'.$counter];
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    public static function isBoardShow(array $layout): bool
    {
        foreach (($layout['data_sources'] ?? []) as $ds) {
            if (! is_array($ds)) {
                continue;
            }
            if (($ds['id'] ?? '') === self::DATA_SOURCE) {
                return true; // 이미 붙은 레이아웃 — containsId 가 걸러 냄
            }
            $endpoint = (string) ($ds['endpoint'] ?? '');
            if (str_contains($endpoint, '/api/modules/sirsoft-board/boards/') && preg_match('#/posts/\{\{\s*route\.id\s*\}\}$#', $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function dataSource(): array
    {
        return [
            'id' => self::DATA_SOURCE,
            'type' => 'api',
            'endpoint' => '/api/plugins/custom-board_comments/posts/{{route.id}}/mode',
            'method' => 'GET',
            'params' => ['slug' => '{{route.slug}}'],
            'auto_fetch' => true,
            'auth_mode' => 'optional',
            'refetchOnMount' => true,
            'fallback' => ['data' => ['replace' => false, 'count' => null]],
            'errorHandling' => [
                '401' => ['handler' => 'suppress'],
                '403' => ['handler' => 'suppress'],
                '404' => ['handler' => 'suppress'],
                '419' => ['handler' => 'suppress'],
                '429' => ['handler' => 'suppress'],
                '500' => ['handler' => 'suppress'],
                '503' => ['handler' => 'suppress'],
            ],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     * @return array<int|string, mixed>
     */
    private static function swapSections(array $nodes, int &$counter): array
    {
        if (! array_is_list($nodes)) {
            return $nodes;
        }
        $out = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                $out[] = $node;

                continue;
            }
            $expr = self::sectionExpr($node);
            if ($expr !== null) {
                $counter++;
                $orig = $node;
                $node['if'] = '{{('.$expr.') && !('.self::REPLACE_EXPR.')}}';
                $out[] = $node;
                $out[] = self::mountNode($orig, $expr, $counter);

                continue;
            }
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::swapSections($node['children'], $counter);
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * 공식 댓글 칸이면 원래 조건식({{ }} 안쪽)을, 아니면 null.
     *
     * @param  array<string, mixed>  $node
     */
    private static function sectionExpr(array $node): ?string
    {
        $if = $node['if'] ?? null;
        if (! is_string($if) || ! str_contains($if, 'use_comment') || ! preg_match(self::SECTION_IF, $if)) {
            return null;
        }
        if (! preg_match('/^\s*\{\{(.*)\}\}\s*$/s', $if, $m) || str_contains($m[1], '{{') || str_contains($m[1], '}}')) {
            return null;
        }
        // 정말 댓글 칸인지: 안쪽에 댓글 목록(post.data.comments)을 그리는 곳이 있어야 함
        $blob = json_encode($node['children'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        if (! preg_match('/post\??\.data\??\.comments\b/', $blob) && ! str_contains($blob, '_comment_section')) {
            return null;
        }

        return trim($m[1]);
    }

    /**
     * @param  array<string, mixed>  $orig
     * @return array<string, mixed>
     */
    private static function mountNode(array $orig, string $expr, int $n): array
    {
        $class = trim((string) ($orig['props']['className'] ?? 'mt-6'));

        return [
            'id' => self::MOUNT_ID.'_'.$n,
            'type' => 'basic',
            'name' => 'Div',
            'if' => '{{('.$expr.') && ('.self::REPLACE_EXPR.')}}',
            'props' => [
                'className' => trim($class.' cbc-cc-host'),
                'data-cbc-cc-post' => '{{post?.data?.id ?? route.id}}',
                'data-cbc-cc-slug' => '{{route.slug}}',
            ],
        ];
    }

    /**
     * 제목 옆 댓글 수: 바꾸기 모드면 custom-comments 댓글 수.
     *
     * @param  array<int|string, mixed>  $nodes
     * @return array<int|string, mixed>
     */
    private static function swapCountText(array $nodes): array
    {
        foreach ($nodes as $i => $node) {
            if (! is_array($node)) {
                continue;
            }
            if (isset($node['text']) && is_string($node['text']) && preg_match(self::COUNT_TEXT, $node['text'])) {
                $node['text'] = '{{('.self::REPLACE_EXPR.' && cbc_board_comments?.data?.count != null) ? cbc_board_comments.data.count : post.data.comment_count}}';
            }
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::swapCountText($node['children']);
            }
            $nodes[$i] = $node;
        }

        return $nodes;
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     */
    private static function containsId(array $nodes, string $prefix): bool
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (isset($node['id']) && is_string($node['id']) && str_starts_with($node['id'], $prefix)) {
                return true;
            }
            if (isset($node['children']) && is_array($node['children']) && self::containsId($node['children'], $prefix)) {
                return true;
            }
        }

        return false;
    }
}

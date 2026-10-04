<?php

namespace Plugins\Custom\BoardComments\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\Custom\BoardComments\Support\LayoutSwap;

/**
 * 0.2.0 바꾸기 모드: 게시글 상세 레이아웃에 custom-comments 상자와 데이터 소스를 더합니다 (테마 파일은 그대로).
 * 어느 쪽을 보일지는 화면이 데이터 소스(cbc_board_comments)의 replace 값으로 고르므로 설정은 레이아웃에 굽지 않습니다.
 * 구조를 못 찾으면 레이아웃을 그대로 돌려줍니다.
 */
class LayoutSwapListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout_extension.after_apply' => ['method' => 'filter', 'priority' => 90, 'type' => 'filter', 'sync' => true],
        ];
    }

    public function handle(...$args): void {}

    public function filter(mixed $layout = null, mixed $templateId = null): mixed
    {
        if (! is_array($layout)) {
            return $layout;
        }
        try {
            return LayoutSwap::apply($layout)[0];
        } catch (\Throwable) {
            return $layout;
        }
    }
}

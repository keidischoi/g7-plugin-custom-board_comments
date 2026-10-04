<?php

namespace Plugins\Custom\BoardComments\Support;

/**
 * 공식 게시판 권한을 게시판 모듈의 판정 그대로 묻습니다 (현재 요청의 회원 기준).
 *
 * - can(): sirsoft-board 의 ChecksBoardPermission(PermissionMiddleware 경유, 비회원 역할 포함)
 * - canViewSecret(): sirsoft-board 의 SecretContentGate (작성자 · 비밀글 읽기 권한 · 관리자 · 비밀번호 확인 토큰)
 * 게시판 모듈 클래스가 없으면 null(모름)을 돌려주고, 부르는 쪽이 안전한 쪽으로 정합니다.
 */
final class BoardPermission
{
    private const TRAIT = 'Modules\\Sirsoft\\Board\\Traits\\ChecksBoardPermission';

    private const GATE = 'Modules\\Sirsoft\\Board\\Support\\SecretContentGate';

    private const POST_MODEL = 'Modules\\Sirsoft\\Board\\Models\\Post';

    /** 검사용: fn(string $kind, string $arg): ?bool  (kind = can | secret) */
    public static ?\Closure $override = null;

    private static ?object $checker = null;

    public static function can(string $identifier): ?bool
    {
        if (self::$override !== null) {
            return (self::$override)('can', $identifier);
        }
        if (! trait_exists(self::TRAIT)) {
            return null;
        }
        try {
            if (self::$checker === null) {
                self::$checker = new class
                {
                    use \Modules\Sirsoft\Board\Traits\ChecksBoardPermission;

                    public function check(string $identifier): bool
                    {
                        return $this->checkPermissionByIdentifier($identifier);
                    }
                };
            }

            return self::$checker->check($identifier);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function canViewSecret(int $postId): ?bool
    {
        if (self::$override !== null) {
            return (self::$override)('secret', (string) $postId);
        }
        if (! class_exists(self::GATE) || ! class_exists(self::POST_MODEL)) {
            return null;
        }
        try {
            $model = (self::POST_MODEL)::withTrashed()->with('board')->find($postId);
            if (! $model) {
                return false;
            }

            return (bool) app(self::GATE)->canView($model);
        } catch (\Throwable) {
            return null;
        }
    }
}

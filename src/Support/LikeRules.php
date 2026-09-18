<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Support;

final class LikeRules
{
    /**
     * @return array{user_id: int, guest_hash: string}|null
     */
    public static function actor(?int $userId, ?string $guestHash, bool $allowGuest): ?array
    {
        if ($userId !== null && $userId > 0) {
            return [
                'user_id' => $userId,
                'guest_hash' => '',
            ];
        }

        $hash = trim((string) $guestHash);
        if ($allowGuest && $hash !== '') {
            return [
                'user_id' => 0,
                'guest_hash' => $hash,
            ];
        }

        return null;
    }

    public static function guestHash(string $ip, string $userAgent = ''): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }

        return hash('sha256', $ip.'|'.trim($userAgent));
    }

    /**
     * @return array{liked: bool, delta: int}
     */
    public static function toggle(bool $alreadyLiked): array
    {
        if ($alreadyLiked) {
            return ['liked' => false, 'delta' => -1];
        }

        return ['liked' => true, 'delta' => 1];
    }
}

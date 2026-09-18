<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Support;

final class ActorRules
{
    public static function id(mixed $authId, mixed $requestUser = null): string
    {
        foreach ([$authId, self::fromUser($requestUser)] as $candidate) {
            if ($candidate === null || $candidate === false) {
                continue;
            }
            $text = trim((string) $candidate);
            if ($text === '' || $text === '0') {
                continue;
            }

            return $text;
        }

        return '';
    }

    private static function fromUser(mixed $user): mixed
    {
        if (! is_object($user)) {
            return null;
        }
        foreach (['uuid', 'id'] as $field) {
            if (isset($user->{$field}) && $user->{$field} !== null && $user->{$field} !== '') {
                return $user->{$field};
            }
        }
        if (method_exists($user, 'getAuthIdentifier')) {
            return $user->getAuthIdentifier();
        }

        return null;
    }
}

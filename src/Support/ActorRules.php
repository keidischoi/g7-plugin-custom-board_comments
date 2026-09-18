<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Support;

use Illuminate\Support\Facades\Auth;

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

    /**
     * G7 plugin API is Bearer-only. Default Auth::id() stays empty unless
     * the sanctum guard actually reads the token.
     */
    public static function fromRequest(mixed $request = null): string
    {
        return self::id(self::authId(), self::requestUser($request));
    }

    private static function authId(): mixed
    {
        if (! class_exists(Auth::class)) {
            return null;
        }

        try {
            $sanctumId = Auth::guard('sanctum')->id();
            if ($sanctumId !== null && $sanctumId !== false && $sanctumId !== '') {
                return $sanctumId;
            }
        } catch (\Throwable) {
            // sanctum guard may be missing in isolated tests
        }

        try {
            return Auth::id();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function requestUser(mixed $request): mixed
    {
        if (! is_object($request) || ! method_exists($request, 'user')) {
            return null;
        }

        try {
            $sanctumUser = $request->user('sanctum');
            if ($sanctumUser !== null) {
                return $sanctumUser;
            }
        } catch (\Throwable) {
            // some request doubles only accept user()
        }

        try {
            return $request->user();
        } catch (\Throwable) {
            return null;
        }
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

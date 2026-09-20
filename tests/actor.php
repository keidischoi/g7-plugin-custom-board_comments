<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Plugins\Custom\BoardComments\Support\ActorRules;

expect('empty auth', ActorRules::id(null, null), '');
expect('zero auth', ActorRules::id(0, null), '');
expect('numeric auth', ActorRules::id(12, null), '12');
expect('uuid auth', ActorRules::id('a2a91405-a411-4f68-b1fb-cf35535402e8', null), 'a2a91405-a411-4f68-b1fb-cf35535402e8');

$user = (object) ['uuid' => 'user-uuid-1', 'id' => 99];
expect('request user uuid', ActorRules::id(null, $user), 'user-uuid-1');
expect('auth wins over request user', ActorRules::id(7, $user), '7');

$sanctumRequest = new class {
    public function user(?string $guard = null): ?object
    {
        if ($guard === 'sanctum') {
            return (object) ['uuid' => 'sanctum-uuid'];
        }

        return (object) ['id' => 1];
    }
};
expect('from request uses sanctum user', ActorRules::fromRequest($sanctumRequest), 'sanctum-uuid');
expect('from request guest', ActorRules::fromRequest(null), '');

finish();

<?php

namespace Postiefs\Sso\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Postiefs\Sso\Data\SsoIdentity;

/**
 * A user signed in through the portal. Listen to it to sync roles, for example
 * `$event->user->syncRoles($event->identity->roles)` with spatie/laravel-permission.
 */
class SsoLoggedIn
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly SsoIdentity $identity,
        public readonly bool $created,
    ) {}
}

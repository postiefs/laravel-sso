<?php

namespace Postiefs\Sso\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Postiefs\Sso\Data\SsoIdentity;

/**
 * The first sign-in of someone without a local user created one. Listen to it to give the new user a starting role.
 */
class SsoUserCreated
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly SsoIdentity $identity,
    ) {}
}

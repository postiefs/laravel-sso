<?php

namespace Postiefs\Sso\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Postiefs\Sso\Data\SsoIdentity;

/**
 * A user signed in through the portal. Listen to it to run your own follow-up.
 */
class SsoLoggedIn
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly SsoIdentity $identity,
        public readonly bool $created,
    ) {}
}

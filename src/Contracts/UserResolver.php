<?php

namespace Postiefs\Sso\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Postiefs\Sso\Data\SsoIdentity;

/**
 * Maps a verified portal identity to a local user. Point `postiefs-sso.user.resolver` at your own implementation to change
 * matching, provisioning or role handling. Return null to refuse the sign-in (the user sees a 403).
 */
interface UserResolver
{
    public function resolve(SsoIdentity $identity): ?Authenticatable;
}

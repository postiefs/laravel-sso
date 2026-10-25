<?php

namespace Postiefs\Sso\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The package signed a user out. The reason is `expired` (the portal's deadline), `revoked` (an administrator ended
 * the session), `unknown_session`, an access denial reason from the portal, or `logout`.
 */
class SsoSessionEnded
{
    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly string $reason,
    ) {}
}

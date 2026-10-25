<?php

namespace Postiefs\Sso\Contracts;

use Postiefs\Sso\Data\AccessCheck;
use Postiefs\Sso\Data\SsoIdentity;
use Postiefs\Sso\Exceptions\InvalidSsoToken;

/**
 * What the app needs from the SSO portal. Bind another implementation to fake the portal in tests.
 */
interface PortalClient
{
    /**
     * Whether single sign-on is set up (app id and secret present).
     */
    public function enabled(): bool;

    /**
     * Check the `sso_token` the portal sent and return who it names.
     *
     * @throws InvalidSsoToken
     */
    public function verifyLaunchToken(string $token): SsoIdentity;

    /**
     * Ask the portal whether the session is alive and the user still allowed. A portal that cannot be reached
     * answers "valid" with `degraded` set: an outage never signs anyone out.
     */
    public function checkAccess(string $email, ?string $sessionId): AccessCheck;

    /**
     * Where the browser goes to sign in with Microsoft.
     */
    public function loginUrl(): string;

    /**
     * The launchpad, where users land after signing out.
     */
    public function portalUrl(): string;
}

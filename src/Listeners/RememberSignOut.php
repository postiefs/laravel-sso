<?php

namespace Postiefs\Sso\Listeners;

use Illuminate\Auth\Events\Logout;
use Postiefs\Sso\SsoSession;

/**
 * A user who signs out of a portal session goes back to the portal. The session is wiped before the response is built,
 * so the fact is remembered here, while it can still be read.
 */
class RememberSignOut
{
    public function __construct(private SsoSession $session) {}

    public function handle(Logout $event): void
    {
        if ($this->session->active()) {
            $this->session->markSignedOut();
        }
    }
}

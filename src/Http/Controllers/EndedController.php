<?php

namespace Postiefs\Sso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Postiefs\Sso\Contracts\PortalClient;

/**
 * GET {prefix}/ended: where the package sends a user it signed out. Back to the launchpad.
 */
class EndedController
{
    public function __invoke(PortalClient $portal): RedirectResponse
    {
        return redirect()->away($portal->portalUrl());
    }
}

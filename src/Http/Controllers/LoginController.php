<?php

namespace Postiefs\Sso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Exceptions\SignInFailed;
use Postiefs\Sso\Support\SsoLog;

/**
 * GET {prefix}/login: hands the browser to the portal, which signs the user in with Microsoft.
 */
class LoginController
{
    public function __invoke(PortalClient $portal, SsoLog $log): RedirectResponse
    {
        throw_unless($portal->enabled(), SignInFailed::notEnabled());

        $log->debug('sending the browser to '.$portal->loginUrl());

        return redirect()->away($portal->loginUrl());
    }
}

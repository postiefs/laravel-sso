<?php

namespace Postiefs\Sso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Events\SsoSessionEnded;
use Postiefs\Sso\SsoSession;

/**
 * POST {prefix}/logout: for apps without their own sign-out (Filament has one). Signs out and returns to the launchpad.
 */
class LogoutController
{
    public function __invoke(Request $request, PortalClient $portal, SsoSession $session): RedirectResponse
    {
        $guard = Auth::guard(config('postiefs-sso.guard', 'web'));
        $user = $guard->user();

        $guard->logout();
        $session->forget();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        event(new SsoSessionEnded($user, 'logout'));

        return redirect()->away($portal->portalUrl());
    }
}

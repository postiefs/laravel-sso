<?php

namespace Postiefs\Sso\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Postiefs\Sso\SsoSession;

/**
 * GET {prefix}/heartbeat: polled by the browser guard. EnsureSsoSession does the real checking; this answers 401 once
 * the user is no longer signed in.
 */
class HeartbeatController
{
    public function __invoke(SsoSession $session): JsonResponse
    {
        if (! Auth::guard(config('postiefs-sso.guard', 'web'))->check()) {
            return response()->json(['valid' => false, 'sessionExpired' => true], 401);
        }

        return response()->json(['valid' => true, 'secondsRemaining' => $session->secondsRemaining()]);
    }
}

<?php

namespace Postiefs\Sso\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Postiefs\Sso\Contracts\PortalClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * `sso.auth`: like Laravel's `auth`, but sends guests to the portal's sign-in. The URL asked for is remembered and
 * restored after the callback.
 */
class Authenticate
{
    public function __construct(private PortalClient $portal) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard(config('postiefs-sso.guard', 'web'))->check()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return response()->json([
                'error' => 'Not authenticated',
                'sessionExpired' => true,
                'loginUrl' => $this->portal->loginUrl(),
            ], 401);
        }

        return redirect()->guest(route('sso.login'));
    }
}

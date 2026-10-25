<?php

namespace Postiefs\Sso\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Events\SsoSessionEnded;
use Postiefs\Sso\SsoSession;
use Postiefs\Sso\Support\SsoLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * `sso.session`: enforces the portal's session rules on every request.
 *
 *  1. the absolute deadline, with no network call
 *  2. a check with the portal at most every `postiefs-sso.check_interval` seconds, which catches a session an administrator
 *     ended and access that was removed
 *
 * A user who signed in locally has no portal session and is left alone.
 */
class EnsureSsoSession
{
    public function __construct(
        private PortalClient $portal,
        private SsoSession $session,
        private SsoLog $log,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard(config('postiefs-sso.guard', 'web'));

        if (! $guard->check() || ! $this->session->active()) {
            return $next($request);
        }

        if ($this->session->expired()) {
            return $this->signOut($request, 'expired');
        }

        if ($this->session->dueForCheck((int) config('postiefs-sso.check_interval', 60))) {
            $check = $this->portal->checkAccess((string) $guard->user()->email, $this->session->sessionId());

            $this->session->markChecked();

            $this->log->debug('portal check', ['valid' => $check->valid, 'reason' => $check->reason, 'degraded' => $check->degraded]);

            if (! $check->valid) {
                return $this->signOut($request, $check->reason);
            }
        }

        return $next($request);
    }

    private function signOut(Request $request, string $reason): Response
    {
        $guard = Auth::guard(config('postiefs-sso.guard', 'web'));
        $user = $guard->user();

        $this->log->info('signing out '.($user->email ?? '?')." ({$reason})");

        $guard->logout();
        $this->session->forget();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        event(new SsoSessionEnded($user, $reason));

        // Livewire and fetch requests cannot follow a redirect: the browser guard turns the 401 into one.
        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return response()->json(['sessionExpired' => true, 'reason' => $reason], 401);
        }

        return redirect()->route('sso.ended');
    }
}

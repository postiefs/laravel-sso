<?php

namespace Postiefs\Sso\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Support\SsoLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware. A portal redirect that lands on any page but the callback (the Callback URL registered in the
 * portal is the site root or the panel) is never handled, and the user just sees the login page. This logs it and,
 * with `postiefs-sso.stray_token.forward`, sends the browser on to the real callback, which verifies the token as usual.
 */
class HandleStrayToken
{
    public function __construct(private PortalClient $portal, private SsoLog $log) {}

    public function handle(Request $request, Closure $next): Response
    {
        $callbackPath = trim((string) config('postiefs-sso.routes.prefix', 'sso'), '/').'/callback';

        $stray = config('postiefs-sso.stray_token.detect', true)
            && $this->portal->enabled()
            && $request->isMethod('GET')
            && ! $request->is($callbackPath)
            && ($request->query->has('sso_token') || $request->query->has('sso_error'));

        if (! $stray) {
            return $next($request);
        }

        $forward = (bool) config('postiefs-sso.stray_token.forward', true);

        $this->log->warning('the portal sent the user to /'.$request->path()." instead of /{$callbackPath}: fix the Callback URL registered in the portal".($forward ? ' (forwarded)' : ''));

        if (! $forward) {
            return $next($request);
        }

        return redirect()->to(url($callbackPath).'?'.http_build_query($request->only(['sso_token', 'sso_error'])));
    }
}

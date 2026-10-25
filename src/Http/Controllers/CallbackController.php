<?php

namespace Postiefs\Sso\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Contracts\UserResolver;
use Postiefs\Sso\Events\SsoLoggedIn;
use Postiefs\Sso\Events\SsoUserCreated;
use Postiefs\Sso\Exceptions\InvalidSsoToken;
use Postiefs\Sso\Exceptions\SignInFailed;
use Postiefs\Sso\SsoSession;
use Postiefs\Sso\Support\HomeUrl;
use Postiefs\Sso\Support\SsoLog;

/**
 * GET {prefix}/callback?sso_token=...: the Callback URL registered for this app in the portal.
 */
class CallbackController
{
    public function __invoke(Request $request, PortalClient $portal, UserResolver $users, SsoSession $session, SsoLog $log, HomeUrl $home): RedirectResponse
    {
        $log->debug('callback reached', [
            'enabled' => $portal->enabled(),
            'has_token' => $request->query->has('sso_token'),
            'error' => $request->query('sso_error'),
            'host' => $request->getHost(),
        ]);

        throw_unless($portal->enabled(), SignInFailed::notEnabled());

        if ($error = $request->query('sso_error')) {
            $log->warning('the portal refused the sign-in: '.$error);

            throw SignInFailed::refusedByPortal((string) $error);
        }

        $token = (string) $request->query('sso_token', '');

        throw_if($token === '', SignInFailed::missingToken());

        try {
            $identity = $portal->verifyLaunchToken($token);
        } catch (InvalidSsoToken $e) {
            $log->warning('launch token rejected: '.$e->getMessage());

            throw SignInFailed::invalidToken();
        }

        $log->debug('launch token accepted', ['email' => $identity->email, 'session' => $identity->sessionId]);

        // A launch token works once.
        throw_unless(
            Cache::store(config('postiefs-sso.launch_cache'))->add('sso:launch:'.hash('sha256', $token), 1, now()->addMinutes(5)),
            SignInFailed::tokenAlreadyUsed(),
        );

        $user = $users->resolve($identity);

        if ($user === null) {
            $log->warning('no local user may sign in for '.$identity->email);

            throw SignInFailed::userNotAllowed();
        }

        $created = (bool) ($user->wasRecentlyCreated ?? false);

        $log->debug('user resolved', ['id' => $user->getAuthIdentifier(), 'created' => $created]);

        Auth::guard(config('postiefs-sso.guard', 'web'))->login($user);
        $request->session()->regenerate();
        $session->start($identity);

        if ($created) {
            event(new SsoUserCreated($user, $identity));
        }

        event(new SsoLoggedIn($user, $identity, $created));

        $target = redirect()->intended($home->get());

        $log->debug('signed in, redirecting to '.$target->getTargetUrl());

        return $target;
    }
}

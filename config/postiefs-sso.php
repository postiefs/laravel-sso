<?php

use Postiefs\Sso\Support\DefaultUserResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Portal connection
    |--------------------------------------------------------------------------
    |
    | Single sign-on is off until `app_id` and `secret` are set. It runs next to your own login: nothing here
    | touches local accounts.
    |
    | app_id      The app id registered in the SSO portal. It is the `aud` of every launch token.
    | secret      EXACTLY the portal's SSO_JWT_SECRET. The portal owns it; never generate your own.
    | url         Server to server address of the portal API (the session check). Prefer an internal address.
    | public_url  The address the BROWSER is sent to for the Microsoft sign-in. Falls back to `url`.
    | portal_url  The launchpad, where users land after signing out.
    |
    */

    'app_id' => env('SSO_APP_ID'),

    'secret' => env('SSO_JWT_SECRET'),

    'url' => rtrim((string) env('SSO_URL', ''), '/'),

    'public_url' => rtrim((string) env('SSO_PUBLIC_URL', env('SSO_URL', '')), '/'),

    'portal_url' => env('SSO_PORTAL_URL', '/'),

    'issuer' => 'sso-portal',

    'check_issuer' => 'app-check',

    /*
    |--------------------------------------------------------------------------
    | Session behaviour
    |--------------------------------------------------------------------------
    |
    | check_interval  Seconds between two checks with the portal (ended by an administrator, access removed).
    | leeway          Clock skew allowed when validating a token, in seconds.
    | timeout         HTTP timeout of the check. A portal that cannot be reached never signs anyone out.
    | fallback_hours  Session length when the portal sends no deadline (`sexp`).
    | launch_cache    Cache store that remembers used launch tokens (single use). Null = the default store.
    | session_key     Where the SSO state lives in the Laravel session.
    |
    | The Laravel session lifetime must be at least as long as the portal's (SESSION_LIFETIME=720 for 12 hours),
    | or the app signs the user out before the portal's deadline.
    |
    */

    'check_interval' => (int) env('SSO_CHECK_INTERVAL', 60),

    'leeway' => 30,

    'timeout' => 5,

    'fallback_hours' => 8,

    'launch_cache' => env('SSO_LAUNCH_CACHE'),

    'session_key' => 'sso',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Registers {prefix}/login, /callback, /ended, /heartbeat and /logout. Set this app's Callback URL in the portal
    | to exactly https://your-app.example.com/{prefix}/callback.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => env('SSO_ROUTE_PREFIX', 'sso'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | A launch token that lands on the wrong page
    |--------------------------------------------------------------------------
    |
    | When the Callback URL registered in the portal is wrong (the site root, `/admin`), the token arrives on a page
    | that ignores it and the user just sees the login page. `detect` logs that; `forward` sends the browser on to the
    | real callback, which verifies the token as usual, so a wrong URL still signs the user in.
    |
    */

    'stray_token' => [
        'detect' => true,
        'forward' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    |
    | guard           The auth guard users are signed in on.
    | model           The Eloquent user model.
    | resolver        A Postiefs\Sso\Contracts\UserResolver. Extend DefaultUserResolver to change who may sign in
    |                 (`isAllowed()`), how a user is found (`find()`) or created (`create()`).
    | match_on        The column the token's email is matched against (case insensitive).
    | create_missing  Create a local user the first time someone signs in. Existing users are never changed
    |                 except for the `sync` columns.
    | defaults        Extra attributes of a created user, for example ['status' => 'active'].
    | attributes      column => token claim, written when a user is created. Columns the table does not have are
    |                 skipped, so no migration is required. Claims: sub, email, name, roles,
    |                 department, groups.
    | sync            column => token claim, refreshed on every sign-in.
    |
    */

    'guard' => env('SSO_GUARD', 'web'),

    'user' => [
        'model' => env('SSO_USER_MODEL', 'App\\Models\\User'),
        'resolver' => DefaultUserResolver::class,
        'match_on' => 'email',
        'create_missing' => true,
        'defaults' => [],
        'attributes' => [
            'name' => 'name',
        ],
        'sync' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | home  Where users go after signing in when there is no "intended" URL. Null = "/".
    |       (The Filament package points this at the panel.)
    |
    */

    'home' => env('SSO_HOME'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | channel  Log channel for the `[sso]` lines. Null = the default channel.
    | debug    Log every step of a sign-in (callback reached, token accepted, user resolved, redirect). Turn it on
    |          while connecting an app, off afterwards. Warnings and errors are always logged.
    |
    */

    'log' => [
        'channel' => env('SSO_LOG_CHANNEL'),
        'debug' => (bool) env('SSO_DEBUG', false),
    ],

];

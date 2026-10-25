# Configuration

Every option lives in `config/postiefs-sso.php` (publish it with `php artisan vendor:publish --tag=postiefs-sso-config`).
Read them as `config('postiefs-sso.secret')`, `config('postiefs-sso.user.model')`, and so on.

> **Publishing and merging.** The package merges your file over its own **one level deep**. If you override `user`, `routes`,
> `log` or `stray_token`, your file must contain the whole block, or the keys you leave out are gone. Keep the published file's
> blocks complete and change only the values.

## Portal

| Key | `.env` | Default | |
|---|---|---|---|
| `app_id` | `SSO_APP_ID` | none | The app id registered in the portal; the `aud` of the launch token. |
| `secret` | `SSO_JWT_SECRET` | none | The portal's shared secret. Single sign-on is off until `app_id` and `secret` are set. |
| `url` | `SSO_URL` | `''` | Server to server address of the portal, used for `POST /auth/check-access`. |
| `public_url` | `SSO_PUBLIC_URL` | `SSO_URL` | Where the browser is sent to sign in: `{public_url}/auth/login?app={app_id}`. |
| `portal_url` | `SSO_PORTAL_URL` | `/` | The launchpad, where people land after signing out. |
| `issuer` | | `sso-portal` | Required `iss` of the launch token. |
| `check_issuer` | | `app-check` | `iss` of the token the package sends with a session check. |

## Session

| Key | `.env` | Default | |
|---|---|---|---|
| `check_interval` | `SSO_CHECK_INTERVAL` | `60` | Seconds between two checks with the portal. Also the browser heartbeat interval (at least 15). |
| `leeway` | | `30` | Clock skew allowed when validating a token, in seconds. |
| `timeout` | | `5` | HTTP timeout of the session check. A timeout fails open. |
| `fallback_hours` | | `8` | Session length when the portal sends no `sexp`. |
| `launch_cache` | `SSO_LAUNCH_CACHE` | default store | Cache store that remembers used launch tokens. Must be shared by all servers. |
| `session_key` | | `sso` | Where the portal session lives in the Laravel session. |

## Routes

```php
'routes' => [
    'enabled' => true,
    'prefix' => env('SSO_ROUTE_PREFIX', 'sso'),
    'middleware' => ['web'],
],
```

| Route | Name | |
|---|---|---|
| `GET {prefix}/login` | `sso.login` | Sends the browser to the portal's Microsoft sign-in. |
| `GET {prefix}/callback` | `sso.callback` | The Callback URL registered in the portal. |
| `GET {prefix}/ended` | `sso.ended` | Where a signed-out user is sent; redirects to the launchpad. |
| `GET {prefix}/heartbeat` | `sso.heartbeat` | Polled by the browser guard; `401` when the user is no longer signed in. |
| `POST {prefix}/logout` | `sso.logout` | Signs out and goes to the launchpad. |

Set `enabled` to `false` to register your own routes with the package's controllers. If you change the prefix, register
the new Callback URL in the portal.

## <a name="stray_token"></a>Stray token

```php
'stray_token' => ['detect' => true, 'forward' => true],
```

A global middleware that notices `?sso_token=` or `?sso_error=` on any page other than the callback, which means the
Callback URL registered in the portal is wrong.

- `detect`: log a warning that names the page (`[sso] the portal sent the user to /admin instead of /sso/callback`).
- `forward`: also redirect the browser to the real callback, which verifies the token as usual. A wrong registration then
  still signs people in. Turn it off to only log.

## Users

| Key | Default | |
|---|---|---|
| `guard` (`SSO_GUARD`) | `web` | The auth guard people are signed in on. |
| `user.model` (`SSO_USER_MODEL`) | `App\Models\User` | The Eloquent user model. |
| `user.resolver` | `DefaultUserResolver` | Class implementing `Contracts\UserResolver`. See [Users and roles](users-and-roles.md). |
| `user.match_on` | `email` | Column the token's email is matched against, case insensitively. |
| `user.create_missing` | `true` | Create a user the first time someone signs in. With `false`, people without a local user get a 403. |
| `user.defaults` | `[]` | Extra attributes of a created user, for example `['status' => 'active']`. |
| `user.attributes` | `['name' => 'name']` | `column => claim`, written when a user is created. |
| `user.sync` | `[]` | `column => claim`, refreshed on every sign-in. |

Claims for `attributes` and `sync`: `email`, `name`, `sub`, `roles`, `department`, `groups`, `sid`, or any other claim in the
token. Columns the users table does not have are skipped. An array claim is stored as JSON unless the model casts the
column.

## Redirects

`home` (`SSO_HOME`): where people go after signing in when no page was asked for first. Empty means `/`. The Filament
package sets it to the panel automatically.

## Logging

| Key | `.env` | Default | |
|---|---|---|---|
| `log.channel` | `SSO_LOG_CHANNEL` | default channel | Log channel for the `[sso]` lines. |
| `log.debug` | `SSO_DEBUG` | `false` | Log every step of a sign-in. |

Always logged: warnings and errors (token rejected, portal unreachable, secret mismatch, stray token, user refused) and
sign-outs. With `SSO_DEBUG=true` also: sending the browser to the portal, callback reached, token accepted, user resolved,
redirect target and each portal check. Turn it on while connecting an app and off afterwards.

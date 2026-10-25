# Reference

## Middleware

| Alias | Class | |
|---|---|---|
| `sso` | group of the two below | Sign in through the portal, then enforce its session rules. |
| `sso.auth` | `Http\Middleware\Authenticate` | Like `auth`: guests go to `sso.login` (the requested URL is remembered); JSON and Livewire requests get `401` with `loginUrl`. |
| `sso.session` | `Http\Middleware\EnsureSsoSession` | The deadline, then a portal check at most every `check_interval` seconds. Leaves users without a portal session alone. A signed-out user is redirected to `sso.ended`; JSON and Livewire requests get `401 {"sessionExpired": true, "reason": "..."}`. |
| (global) | `Http\Middleware\HandleStrayToken` | Registered by the package. See [Stray token](configuration.md#stray_token). |

## Events

All in `Postiefs\Sso\Events`.

| Event | When | Properties |
|---|---|---|
| `SsoUserCreated` | the first sign-in created a local user | `user`, `identity` |
| `SsoLoggedIn` | after every sign-in | `user`, `identity`, `created` |
| `SsoSessionEnded` | the package signed someone out | `user` (may be null), `reason` |

`SsoSessionEnded::$reason` is `expired` (the portal's deadline), `revoked` (an administrator ended the session),
`unknown_session`, an access denial reason from the portal (the person lost access to the app), or `logout`.

## `Sso` facade

`Postiefs\Sso\Facades\Sso` reads the portal session of the current request.

| Method | |
|---|---|
| `active()` | signed in through the portal (false for a password login) |
| `sessionId()` | the portal session id |
| `expiresAt()`, `secondsRemaining()`, `expired()` | the deadline |
| `roles()`, `groups()`, `department()`, `hasRole(...$roles)` | what the portal knows |
| `signedOut()` | the user signed out of a portal session in this request (for a logout response) |

## Contracts

| Contract | Default | |
|---|---|---|
| `Contracts\PortalClient` | `SsoClient` | `enabled()`, `verifyLaunchToken()`, `checkAccess()`, `loginUrl()`, `portalUrl()`. Bind another implementation to fake the portal. |
| `Contracts\UserResolver` | `Support\DefaultUserResolver` | identity to local user. See [Users and roles](users-and-roles.md). |

`checkAccess()` returns `Data\AccessCheck { valid, reason, secondsRemaining, expiresAt, degraded }`; a portal that cannot be
reached gives `AccessCheck::degraded()` (valid).

## Exceptions

| Exception | |
|---|---|
| `InvalidSsoToken` | a launch token that cannot be trusted. Thrown by `verifyLaunchToken()`, handled by the callback. |
| `SsoNotConfigured` | `app_id` or `secret` is empty. |
| `SignInFailed` | an `HttpException`, so Laravel renders its error page with the status: 404 not set up, 400 no token, 401 invalid or used token, 403 refused by the portal or by the resolver. Messages are in `resources/lang/en/messages.php`. |

## Commands

| Command | |
|---|---|
| `sso:install [--migration]` | publish the config; print the `.env` keys and the Callback URL |
| `sso:check` | test the settings, that the portal answers, and that the secret matches. Exit code 1 on a failure |

## Blade

| | |
|---|---|
| `@ssoSessionGuard` | the browser guard script (renders only for a portal session) |
| `<x-postiefs-sso::login-button />` | a "Sign in with Microsoft 365" link to `sso.login`; renders only when single sign-on is configured. Props: `label` |

## Publish tags

`postiefs-sso-config`, `postiefs-sso-migrations`, `postiefs-sso-views`, `postiefs-sso-lang`.

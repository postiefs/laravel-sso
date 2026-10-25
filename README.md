# postiefs/laravel-sso

Sign in to a Laravel app through the Postiefs SSO portal (Microsoft Entra ID). The portal owns the session: this package
trusts its launch token, mirrors its deadline, and signs the user out when an administrator ends the session or removes
their access. Local logins keep working next to it.

For a Filament panel add [`postiefs/laravel-filament-sso`](https://github.com/postiefs/laravel-filament-sso) on top.

## Documentation

- [Installation](docs/installation.md): requirements, `.env`, the Callback URL, protecting routes, deploying
- [How it works](docs/how-it-works.md): the launch token, the session, the check with the portal, fail open
- [Configuration](docs/configuration.md): every option
- [Users and roles](docs/users-and-roles.md): finding and creating users, who may sign in, roles by event
- [Reference](docs/reference.md): middleware, events, facade, contracts, exceptions, commands, Blade
- [Testing](docs/testing.md): launch tokens and a fake portal in your tests
- [Troubleshooting](docs/troubleshooting.md): a sign-in that does nothing, sessions that end too early or never

## How it works

```
Launchpad "Open"  ->  portal re-checks access  ->  302 {Callback URL}?sso_token=<JWT, 60s>
                                                          |
App   GET /sso/callback   verify the token (HS256, iss, aud, exp), single use, find or create the user, sign in
App   every request       deadline reached? signed out. Every 60s: POST {SSO_URL}/auth/check-access
Browser                   timer for the deadline + a heartbeat, so an idle tab is signed out too
```

Launch token claims: `iss=sso-portal`, `aud=<app id>`, `exp` (about 60s, the token itself), `sexp` (the session's
absolute deadline, adopted as is and never extended), `jti` (the portal session id), `email`, `name`, `sub`, `roles`,
`department`, `groups`.

If the portal cannot be reached the user stays signed in (fail open): an outage never locks everyone out.

## Install

```bash
composer require postiefs/laravel-sso
php artisan sso:install
```

`sso:install` publishes `config/postiefs-sso.php`, prints the `.env` keys and the **Callback URL to register in the portal**
(`https://your-app.example.com/sso/callback`, exactly). A Callback URL that points anywhere else is the usual reason a
sign-in "does nothing"; with `postiefs-sso.stray_token.forward` on (the default) the package forwards a token that landed on the
wrong page, and logs a warning that names the page.

```dotenv
SSO_APP_ID=            # the id registered in the portal (the token `aud`)
SSO_JWT_SECRET=        # EXACTLY the portal's SSO_JWT_SECRET. The portal owns it, never generate your own
SSO_URL=               # server to server address of the portal (the session check), prefer an internal one
SSO_PUBLIC_URL=        # the address the browser is sent to for the Microsoft sign-in
SSO_PORTAL_URL=        # the launchpad, where users land after signing out
SESSION_LIFETIME=720   # at least as long as the portal's session (12 hours)
```

Single sign-on is off until `SSO_APP_ID` and `SSO_JWT_SECRET` are set. Test the connection and the secret:

```bash
php artisan sso:check
```

## Use

Protect routes with the `sso` middleware group (sign in through the portal + the portal's session rules), or only
the session rules on routes that already have your own `auth`:

```php
Route::middleware(['web', 'sso'])->group(...);           // guests go to the portal
Route::middleware(['web', 'auth', 'sso.session'])->...;  // keep your own login, add the portal's rules
```

In a Blade layout (no Filament) add the browser guard and, on your login page, the button:

```blade
@ssoSessionGuard
<x-postiefs-sso::login-button />
```

Read the portal session anywhere:

```php
use Postiefs\Sso\Facades\Sso;

Sso::active();            // signed in through the portal (false for a local login)
Sso::secondsRemaining();
Sso::hasRole('editor');   // the portal's `admin` role always passes
Sso::groups(); Sso::department();
```

## Users

`postiefs-sso.user` decides how the token's email becomes a local user. By default a user is found by email (case insensitive)
or created with a random password; **an existing user is never changed**, except the columns listed in `postiefs-sso.user.sync`.

| Setting | |
|---|---|
| `model`, `guard`, `match_on` | the user model, the auth guard, the column matched against the email |
| `create_missing` | create a user the first time someone signs in |
| `defaults` | extra attributes of a created user, for example `['status' => 'active']` |
| `attributes` | `column => claim`, written when a user is created (columns the table lacks are skipped) |
| `sync` | `column => claim`, refreshed on every sign-in |

The columns `sso_sub`, `department`, `sso_roles` and `sso_groups` are optional:
`php artisan vendor:publish --tag=postiefs-sso-migrations`.

### Who may sign in, and roles

Extend the resolver and point `postiefs-sso.user.resolver` at it:

```php
class AppUserResolver extends \Postiefs\Sso\Support\DefaultUserResolver
{
    protected function isAllowed(Model&Authenticatable $user, SsoIdentity $identity): bool
    {
        return $user->status !== UserStatus::Inactive;   // a refused user sees a 403
    }
}
```

Give a new user a starting role, or sync roles on every sign-in, with events:

```php
Event::listen(SsoUserCreated::class, fn ($event) => $event->user->assignRole('Team Member'));
Event::listen(SsoLoggedIn::class, fn ($event) => $event->user->syncRoles($event->identity->roles));
```

Events: `SsoUserCreated`, `SsoLoggedIn` (with `created`), `SsoSessionEnded` (reason: `expired`, `revoked`,
`unknown_session`, a portal denial reason, `logout`).

## Logging

Every line starts with `[sso]`. Warnings and errors are always written (token rejected, portal unreachable, secret
mismatch, stray token). Set `SSO_DEBUG=true` to log each step of a sign-in while connecting an app (callback reached,
token accepted, user resolved, redirect target), and `SSO_LOG_CHANNEL` to send them to their own channel.

## Testing your app

```php
use Postiefs\Sso\Testing\LaunchToken;

$this->get('/sso/callback?sso_token='.LaunchToken::make(['email' => 'jane@example.com', 'roles' => ['editor']]))
    ->assertRedirect();
```

Bind a fake `Postiefs\Sso\Contracts\PortalClient` to fake the portal.

## Extending

| Contract | |
|---|---|
| `Contracts\PortalClient` | talks to the portal (verify the token, check a session) |
| `Contracts\UserResolver` | identity to local user |

Exceptions: `InvalidSsoToken`, `SsoNotConfigured`, and `SignInFailed` (an `HttpException`, so Laravel renders the error
page with the right status). Messages live in `resources/lang/en/messages.php` (`php artisan vendor:publish --tag=postiefs-sso-lang`).

## Tests

```bash
composer install
vendor/bin/pest
```

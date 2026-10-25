# Installation

Requirements: PHP 8.2+, Laravel 11, 12 or 13. The package depends on `firebase/php-jwt`.

## 1. Install

```bash
composer require postiefs/laravel-sso
php artisan sso:install
```

`sso:install` publishes `config/postiefs-sso.php`, prints the `.env` keys, and prints the **Callback URL** your app needs.

Until the package is listed on Packagist, add the GitHub repository to the app's `composer.json` first (a private repository needs a GitHub token in Composer's `auth.json`):

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/postiefs/laravel-sso" }
]
```

For local development, a path repository works too:

```json
"repositories": [
    { "type": "path", "url": "../packages/laravel-sso", "options": { "symlink": true, "versions": { "postiefs/laravel-sso": "1.0.0" } } }
]
```

## 2. Ask the SSO team for

| Value | `.env` key | Notes |
|---|---|---|
| the app id registered for you | `SSO_APP_ID` | equals the `aud` of every token |
| the shared secret | `SSO_JWT_SECRET` | **exactly** the portal's `SSO_JWT_SECRET`. The portal owns it; do not generate your own. Never commit it |
| the portal's API address | `SSO_URL` | used by the server for the session check; prefer an internal address |
| the browser-facing address | `SSO_PUBLIC_URL` | where `/sso/login` sends the browser. Falls back to `SSO_URL` |
| the launchpad address | `SSO_PORTAL_URL` | where people land after signing out |

And ask them to register this Callback URL for the app, **exactly**:

```
https://your-app.example.com/sso/callback
```

```dotenv
SSO_APP_ID=email-inventory
SSO_JWT_SECRET=...
SSO_URL=http://127.0.0.1:5051
SSO_PUBLIC_URL=https://ssoapi.example.com
SSO_PORTAL_URL=https://sso.example.com
SESSION_LIFETIME=720
```

`SESSION_LIFETIME` is in minutes and must be at least as long as the portal's session (720 = 12 hours). If it is
shorter, Laravel ends the session before the portal's deadline and people have to sign in again early.

Single sign-on stays **off** until `SSO_APP_ID` and `SSO_JWT_SECRET` are both set.

## 3. Check it

```bash
php artisan sso:check
```

It tells you which values are missing, whether the portal answers at `SSO_URL`, and whether `SSO_JWT_SECRET` matches the
portal's (a `401` means it does not). It also prints the Callback URL for your app.

## 4. Protect routes and add the button

```php
// Guests are sent to the portal; signed-in users get the portal's session rules.
Route::middleware(['web', 'sso'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

// Keep your own login and add only the portal's session rules to it.
Route::middleware(['web', 'auth', 'sso.session'])->group(...);
```

In a Blade layout (use the Filament package for a Filament panel):

```blade
{{-- login page --}}
<x-postiefs-sso::login-button />

{{-- layout: before </body>, so an idle tab is signed out at the portal's deadline --}}
@ssoSessionGuard
```

The button renders nothing until single sign-on is configured. To sign out of a portal session, post to `route('sso.logout')`;
it ends the session and sends the person to the launchpad.

## 5. Optional

```bash
php artisan vendor:publish --tag=postiefs-sso-views        # the guard script and the button
php artisan vendor:publish --tag=postiefs-sso-lang         # the error messages
```

## Deploying

- Set the `.env` keys on the server, then `php artisan config:clear` (or `config:cache` again).
- Behind a load balancer, use a shared cache (`SSO_LAUNCH_CACHE` or the default store) so a launch token cannot be used
  once on each server, and a shared session driver.
- The server must reach `SSO_URL`. A firewall that blocks it makes the check fail open: nobody is signed out when an
  administrator ends their session.

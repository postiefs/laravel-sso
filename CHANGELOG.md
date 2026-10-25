# Changelog

All notable changes to this package are documented here, newest first.

## [Unreleased]

## [1.0.1]

- Removed the optional users table migration and `sso:install --migration`; the package adds no columns. Roles stay in the app (e.g. spatie/laravel-permission), the portal's roles are never applied to users.

## [1.0.0]

- Launch token verification (HS256, issuer, audience, expiry), single use, with `sexp` adopted as the session deadline.
- Portal session check (`/auth/check-access`) at most every `check_interval` seconds; fails open.
- Callback, login, ended, heartbeat and logout routes; `sso`, `sso.auth` and `sso.session` middleware.
- `UserResolver` contract and `DefaultUserResolver` (find or create by email, never changes an existing user).
- Events `SsoUserCreated`, `SsoLoggedIn`, `SsoSessionEnded`; `Sso` facade; browser guard view and `@ssoSessionGuard`.
- Stray token handling (a wrong Callback URL is logged and forwarded), `[sso]` logging with a debug switch.
- `sso:install`, `sso:check`; `LaunchToken` test helper.

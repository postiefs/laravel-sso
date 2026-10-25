# How it works

The SSO portal signs people in with Microsoft Entra ID and owns their session. An app never sees a Microsoft password
or token. It trusts a short token from the portal, copies the portal's session deadline, and keeps asking the portal
whether the session is still alive.

## The sign-in

```
 1  Launchpad "Open"      portal re-checks access, then redirects the browser to
                          {Callback URL}?sso_token=<JWT, ~60 s>
    or
    /sso/login            sends the browser to {SSO_PUBLIC_URL}/auth/login?app={SSO_APP_ID}
                          (Microsoft sign-in, then step 1 above)

 2  GET /sso/callback     the package verifies the token, finds or creates the local user, signs them in,
                          stores the portal session in the Laravel session, redirects to the page they asked for
```

### The launch token

An HS256 JWT signed with the shared secret (`SSO_JWT_SECRET`). The package accepts it only when all of this holds:

| Check | Why |
|---|---|
| signature valid with `SSO_JWT_SECRET` | it came from the portal |
| `iss` is `sso-portal` | right issuer |
| `aud` contains `SSO_APP_ID` | made for **this** app; a token for another app cannot be replayed here |
| `exp` not passed (30 s leeway) | it lives about 60 seconds |
| `email` present | there is someone to sign in |
| not used before | single use, remembered in the cache for 5 minutes |

Claims the package reads:

| Claim | Meaning | Where it ends up |
|---|---|---|
| `email` | the person (lower-cased) | the user is matched on it |
| `name` | display name | `SsoIdentity::$name` |
| `sub` | Entra object id | `SsoIdentity::$sub` |
| `roles`, `groups`, `department` | what the portal knows about them | `SsoIdentity`, `Sso::roles()` and so on |
| `jti` | the portal session id | sent back with every session check |
| `sexp` | the **absolute deadline** of the session (unix seconds) | `Sso::expiresAt()`; adopted as it is and never extended. Without it the session lasts `fallback_hours` |

When the portal refuses someone it redirects to the Callback URL with `?sso_error=<reason>` instead of a token. The
package answers 403 with the reason.

### The Callback URL

The portal redirects to the Callback URL registered for the app, so it must be exactly
`https://your-app.example.com/sso/callback`. A URL that points at the site root or at `/admin` sends the token to a page
that ignores it, and the user just sees the login page. The package forwards such a token to the callback and logs a
warning (see [Configuration](configuration.md#stray_token)), but the registration should be fixed anyway.

## The session

After sign-in the package keeps `{sid, exp, checked_at, email, roles, groups, department}` in the Laravel session under
the key `postiefs-sso.session_key`. A user who signed in some other way (a password) has none of it, and every rule below
leaves them alone.

On every request that goes through the `sso` or `sso.session` middleware:

1. **The deadline.** If `exp` has passed, the user is signed out. No network call.
2. **The portal check.** At most every `check_interval` seconds (60) the package calls the portal:

   ```
   POST {SSO_URL}/auth/check-access
   { "token": <JWT signed with the same secret, iss = "app-check", payload { email, app, jti, iat, exp } > }
   ```

   Answer: `{ allowed, sessionValid, reason, expiresAt, secondsRemaining, degraded? }`.
   The user is signed out when `allowed` or `sessionValid` is `false` (an administrator ended the session, or the person
   lost access to the app). `reason` is `revoked`, `expired`, `unknown_session` or a denial reason.

3. **Fail open.** If the portal cannot be reached (timeout, 5xx, an answer that is not JSON) the user stays signed in and
   a warning is logged. A portal outage never locks everyone out.

A `401` from the portal means the **secret does not match**. That is logged as an error and also fails open, so check the
log after connecting an app (or run `php artisan sso:check`).

### In the browser

`@ssoSessionGuard` (or the Filament plugin) adds a small script to the page:

- a timer that sends the browser to `/sso/ended` at the deadline, without a request;
- a heartbeat (`GET /sso/heartbeat`, every `check_interval` seconds and when the tab becomes visible again) that notices a
  session the portal ended while the tab sat idle;
- a hook that turns a `401` from a Livewire request into the same redirect.

`/sso/ended` sends the browser to the launchpad (`SSO_PORTAL_URL`).

## What the package never does

- It never stores or logs the launch token.
- It never extends the portal's deadline.
- It never changes a local user's password, and never signs out a user who has no portal session.
- It never talks to Microsoft. Only the portal does.

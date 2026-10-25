# Troubleshooting

Start with `php artisan sso:check` and the log: `grep "\[sso" storage/logs/laravel.log | tail -30`. Set `SSO_DEBUG=true`
to log every step of a sign-in.

## Signing in "does nothing"

You sign in at the portal and end up on the app's login page.

| Log shows | Cause | Fix |
|---|---|---|
| `the portal sent the user to /... instead of /sso/callback` | The Callback URL registered in the portal is wrong (the site root, `/admin`) | Ask the SSO team to register exactly `https://your-app/sso/callback`. With `stray_token.forward` on, people are signed in anyway |
| only `sending the browser to ...` (debug) and nothing after | The portal never redirected to this app: wrong URL registered for another host or environment, the app id is not registered, or the person has no access | Check the registration and the person's access in the portal |
| `callback reached` with `enabled: false` | `SSO_APP_ID` or `SSO_JWT_SECRET` empty on this server | Set them, then `php artisan config:clear` |
| `launch token rejected: Signature verification failed` | `SSO_JWT_SECRET` differs from the portal's | Get the portal's value (`sso:check` shows a mismatch) |
| `launch token rejected: Token was issued for a different application` | `SSO_APP_ID` differs from the id the portal puts in `aud` | Use the id the portal has registered |
| `launch token rejected: Unexpected issuer` | The token is not from the portal | Check `SSO_URL`/`SSO_PUBLIC_URL` point at the right portal |
| `launch token rejected: Expired token` | Server clock off by more than 30 s, or the person waited more than a minute | Sync the clock (NTP) |
| `the portal refused the sign-in: ...` (403) | The portal denied access (`sso_error`) | The person needs access to the app in the portal |
| `no local user may sign in for ...` (403) | `create_missing` is off, or `isAllowed()` refused | Check the resolver and the user |
| "This sign-in link has already been used" (401) | The same link was opened twice, or the cache is not shared between servers | Open the app from the launchpad again; use a shared cache |

## Signed out too early, or not at all

| Symptom | Cause |
|---|---|
| Signed out well before the portal's deadline | `SESSION_LIFETIME` shorter than the portal's session. Set 720 |
| Ending a session in the portal changes nothing | The session check fails open. Look for `[sso] could not reach ...`: `SSO_URL` unreachable from the server, a TLS chain problem, a firewall. Or `the portal rejected the check token`: the secret differs |
| Signed out every minute | `check-access` answers `allowed: false`; the `signing out ... (reason)` line says why |
| An idle tab stays open after the deadline | The browser guard is not on the page: add `@ssoSessionGuard` (or the Filament plugin) |
| A Livewire action shows an error instead of going to the launchpad | `sso.session` is not on the Livewire update request. In Filament use the plugin; elsewhere add the middleware with `isPersistent`/`Livewire::addPersistentMiddleware` |

## Other

- **After deploying nothing changed:** `php artisan config:clear` (and `route:clear`). The package's settings are cached with the rest.
- **"No hint path defined for [postiefs-sso]":** the service provider is not registered. Run `composer dump-autoload`; the provider is auto-discovered.
- **`sso:check` says the portal does not answer:** `SSO_URL` is the address the **server** can reach, not the one the browser uses. Use `SSO_PUBLIC_URL` for the browser.
- **People with a local account:** their account is used; the name and role are not changed. To block a password login for them, do it in the app; the package does not touch passwords.

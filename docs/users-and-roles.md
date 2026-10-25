# Users and roles

## What happens to the user

The callback turns the token's email into a local user:

1. **Find** the user whose `email` (or `user.match_on` column) equals the token's email, ignoring case.
2. **Create** one if there is none and `user.create_missing` is on. It gets the email, a random password nobody knows,
   the `user.defaults`, and the `user.attributes`.
3. **Check** that the user may sign in (`isAllowed()`). If not, the person sees a 403.
4. **Sync** the `user.sync` columns of an existing user.
5. Sign in on `user.guard`.

**An existing user is never changed**, other than the `sync` columns. Their name, role and password stay as they are, so
people who already have a local account keep it and both ways in work.

## Who may sign in

Extend `DefaultUserResolver` and point `postiefs-sso.user.resolver` at your class:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Postiefs\Sso\Data\SsoIdentity;
use Postiefs\Sso\Support\DefaultUserResolver;

class AppUserResolver extends DefaultUserResolver
{
    protected function isAllowed(Model&Authenticatable $user, SsoIdentity $identity): bool
    {
        return $user->status !== UserStatus::Inactive && str_ends_with($identity->email, '@example.com');
    }
}
```

| Method | Override it to |
|---|---|
| `isAllowed($user, $identity)` | refuse someone (inactive user, blocked domain). Default: everyone |
| `find($identity)` | find users another way (a different table, a stored id) |
| `create($identity)` | create users yourself (extra columns, a tenant) |

For something completely different, implement `Postiefs\Sso\Contracts\UserResolver` (`resolve(SsoIdentity): ?Authenticatable`;
return `null` to refuse).

## Roles and permissions

The package does not manage roles and adds no columns or migrations. Roles stay in your app (for example spatie/laravel-permission); the portal's roles are never applied to users. Use the events.

A starting role for people created by their first sign-in:

```php
Event::listen(SsoUserCreated::class, function (SsoUserCreated $event) {
    $event->user->assignRole('Team Member');
});
```

If you ever need the portal's claims, read them from the current session without storing them:

```php
use Postiefs\Sso\Facades\Sso;

Sso::hasRole('editor');   // true for the portal's own `admin` role too
Sso::roles(); Sso::groups(); Sso::department();
```

These read the current portal session and are empty for a user who signed in with a password.

## `SsoIdentity`

What the token says about the person, passed to the resolver and the events:

| Property | |
|---|---|
| `email` | lower-cased |
| `name` | the token's name, else the email |
| `sub` | Entra object id |
| `roles`, `groups` | lists of strings |
| `department` | string |
| `sessionId` | the portal session id (`jti`) |
| `expiresAt` | the session deadline, unix seconds |
| `claims` | every claim of the token |

`claim('name')` reads a claim by its short name; `hasRole('editor')` is true for the portal's `admin` role as well.

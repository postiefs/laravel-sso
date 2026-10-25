# Testing your app

## Sign in with a launch token

`Postiefs\Sso\Testing\LaunchToken` makes tokens the same way the portal does, with your app's `postiefs-sso` config:

```php
use Postiefs\Sso\Testing\LaunchToken;

config(['postiefs-sso.app_id' => 'my-app', 'postiefs-sso.secret' => 'a-shared-secret-of-at-least-32-bytes!!']);

$token = LaunchToken::make([
    'email' => 'jane@example.com',
    'name' => 'Jane Doe',
    'roles' => ['editor'],
    'jti' => 'session-1',
    'sexp' => time() + 3600,     // the session deadline
]);

$this->get('/sso/callback?sso_token='.$token)->assertRedirect();
$this->assertAuthenticated();
```

Any claim can be overridden. Useful variants: `['aud' => 'other-app']`, `['iss' => 'someone-else']`, `['exp' => time() - 60]`
(expired), `['secret' => 'another-secret-of-at-least-32-bytes']` (wrong signature).

HS256 needs a secret of at least 32 bytes in current `firebase/php-jwt`; use a long test secret.

## Fake the portal's session check

```php
Http::fake(['portal.test/*' => Http::response(['allowed' => false, 'sessionValid' => false, 'reason' => 'revoked'])]);
config(['postiefs-sso.url' => 'http://portal.test']);

$this->get('/sso/callback?sso_token='.LaunchToken::make());
$this->travel(2)->minutes();            // past check_interval

$this->get('/dashboard')->assertRedirect(route('sso.ended'));
```

`travel()` works: the package reads the clock through Carbon. Without time travel, the check runs again only after
`check_interval` seconds.

Or bind a fake `Contracts\PortalClient` that returns `new AccessCheck(valid: false, reason: 'revoked')` for a session check.

## Assert on events

```php
Event::fake([SsoUserCreated::class, SsoLoggedIn::class]);
...
Event::assertDispatched(SsoUserCreated::class, fn ($e) => $e->user->email === 'jane@example.com');
```

## The package's own tests

```bash
composer install
vendor/bin/pest
```

They use Orchestra Testbench with a SQLite in-memory database; nothing needs a running portal.

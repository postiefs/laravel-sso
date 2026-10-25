<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Data\SsoIdentity;
use Postiefs\Sso\Events\SsoLoggedIn;
use Postiefs\Sso\Events\SsoUserCreated;
use Postiefs\Sso\Facades\Sso;
use Postiefs\Sso\Support\DefaultUserResolver;
use Postiefs\Sso\Testing\LaunchToken;
use Postiefs\Sso\Tests\Fixtures\User;
use Postiefs\Sso\Tests\TestCase;

it('sends the browser to the portal for the Microsoft sign-in', function () {
    $this->get('/sso/login')->assertRedirect('https://sso.example.com/auth/login?app=inventory');
});

it('is off until the app id and the secret are set', function () {
    config(['postiefs-sso.app_id' => null]);

    $this->get('/sso/login')->assertNotFound();
    $this->get('/sso/callback?sso_token=x')->assertNotFound();
});

it('creates the user on the first sign-in and signs them in for the portal deadline', function () {
    Event::fake([SsoLoggedIn::class, SsoUserCreated::class]);

    $deadline = time() + 3600;

    $this->get('/sso/callback?sso_token='.LaunchToken::make(['email' => 'Jane@Example.com', 'name' => 'Jane Doe', 'jti' => 'sid-1', 'sexp' => $deadline, 'roles' => ['editor']]))
        ->assertRedirect('http://localhost');

    $user = User::query()->where('email', 'jane@example.com')->sole();

    expect($user->name)->toBe('Jane Doe')
        ->and(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id)
        ->and(Sso::active())->toBeTrue()
        ->and(Sso::sessionId())->toBe('sid-1')
        ->and(Sso::expiresAt())->toBe($deadline)
        ->and(Sso::hasRole('editor'))->toBeTrue()
        ->and(Sso::hasRole('nobody'))->toBeFalse();

    Event::assertDispatched(SsoUserCreated::class, fn ($event) => $event->user->is($user));
    Event::assertDispatched(SsoLoggedIn::class, fn ($event) => $event->created && $event->identity->email === 'jane@example.com');
});

it('lets the portal admin role pass every role check', function () {
    $this->get('/sso/callback?sso_token='.LaunchToken::make(['roles' => ['admin']]));

    expect(Sso::hasRole('anything'))->toBeTrue();
});

it('signs an existing user in without changing them, and does not create a second one', function () {
    $existing = User::query()->forceCreate(['name' => 'Local Jane', 'email' => 'jane@example.com', 'password' => 'secret']);

    Event::fake([SsoUserCreated::class]);

    $this->get('/sso/callback?sso_token='.LaunchToken::make(['name' => 'Portal Jane']));

    expect(User::query()->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Local Jane')
        ->and(Auth::id())->toBe($existing->id);

    Event::assertNotDispatched(SsoUserCreated::class);
});

it('refreshes only the sync columns of an existing user', function () {
    config(['postiefs-sso.user.sync' => ['name' => 'name']]);

    User::query()->forceCreate(['name' => 'Local Jane', 'email' => 'jane@example.com', 'password' => 'secret']);

    $this->get('/sso/callback?sso_token='.LaunchToken::make(['name' => 'Portal Jane']));

    expect(User::query()->sole()->name)->toBe('Portal Jane');
});

it('applies the default attributes to a created user', function () {
    config(['postiefs-sso.user.defaults' => ['name' => 'Starting name'], 'postiefs-sso.user.attributes' => []]);

    $this->get('/sso/callback?sso_token='.LaunchToken::make());

    expect(User::query()->sole()->name)->toBe('Starting name');
});

it('goes back to the page the user asked for', function () {
    $this->get('/secret')->assertRedirect(route('sso.login'));

    $this->get('/sso/callback?sso_token='.LaunchToken::make())->assertRedirect('http://localhost/secret');
});

it('refuses a sign-in that creates no user when creating is off', function () {
    config(['postiefs-sso.user.create_missing' => false]);

    $this->get('/sso/callback?sso_token='.LaunchToken::make())->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('lets the resolver refuse a user', function () {
    config(['postiefs-sso.user.resolver' => RefusingResolver::class]);

    $this->get('/sso/callback?sso_token='.LaunchToken::make())->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('refuses tokens it cannot trust', function (array $claims, int $status) {
    $this->get('/sso/callback?sso_token='.LaunchToken::make($claims))->assertStatus($status);

    expect(Auth::check())->toBeFalse();
})->with([
    'another app' => [['aud' => 'another-app'], 401],
    'another issuer' => [['iss' => 'someone-else'], 401],
    'another secret' => [['secret' => 'a-different-secret-of-at-least-32-bytes'], 401],
    'expired' => [['exp' => time() - 600], 401],
    'no email' => [['email' => ''], 401],
]);

it('refuses a token used twice', function () {
    $token = LaunchToken::make();

    $this->get('/sso/callback?sso_token='.$token)->assertRedirect();
    $this->app['auth']->guard()->logout();

    $this->get('/sso/callback?sso_token='.$token)->assertStatus(401);
});

it('refuses a callback without a token, and one the portal refused', function () {
    $this->get('/sso/callback')->assertStatus(400);
    $this->get('/sso/callback?sso_error=access_denied')->assertForbidden();
});

it('forwards a token that landed on the wrong page to the callback', function () {
    $this->get('/?sso_token=abc')->assertRedirect(url('sso/callback').'?sso_token=abc');
});

it('only logs a stray token when forwarding is off', function () {
    config(['postiefs-sso.stray_token.forward' => false]);

    $this->get('/?sso_token=abc')->assertOk();
});

it('answers the heartbeat with 401 for a guest', function () {
    $this->getJson('/sso/heartbeat')->assertUnauthorized();
});

it('builds the test token with the config of the app', function () {
    expect(Sso::active())->toBeFalse();

    $this->get('/sso/callback?sso_token='.LaunchToken::make())->assertRedirect();

    expect(TestCase::SECRET)->not->toBeEmpty();
});

class RefusingResolver extends DefaultUserResolver
{
    protected function isAllowed(Model&Authenticatable $user, SsoIdentity $identity): bool
    {
        return false;
    }
}

it('sees a config change made after the client was first used', function () {
    expect(app(PortalClient::class)->enabled())->toBeTrue();

    config(['postiefs-sso.secret' => null]);

    expect(app(PortalClient::class)->enabled())->toBeFalse();
});

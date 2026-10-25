<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Postiefs\Sso\Events\SsoSessionEnded;
use Postiefs\Sso\Facades\Sso;
use Postiefs\Sso\Testing\LaunchToken;
use Postiefs\Sso\Tests\Fixtures\User;

function signInThroughPortal(array $claims = []): void
{
    test()->get('/sso/callback?sso_token='.LaunchToken::make($claims))->assertRedirect();
}

it('keeps a user in while the portal says the session is alive', function () {
    Http::fake(['portal.test/*' => Http::response(['allowed' => true, 'sessionValid' => true, 'reason' => 'active'])]);

    signInThroughPortal(['jti' => 'sid-1']);
    $this->travel(2)->minutes();

    $this->get('/secret')->assertOk()->assertSee('secret page');

    Http::assertSent(function ($request) {
        $claims = (array) JWT::decode($request['token'], new Key(config('postiefs-sso.secret'), 'HS256'));

        return $request->url() === 'http://portal.test/auth/check-access'
            && $claims['iss'] === 'app-check'
            && $claims['app'] === 'inventory'
            && $claims['email'] === 'jane@example.com'
            && $claims['jti'] === 'sid-1';
    });
});

it('checks with the portal only once per interval', function () {
    Http::fake(['portal.test/*' => Http::response(['allowed' => true, 'sessionValid' => true])]);

    signInThroughPortal();
    $this->travel(2)->minutes();

    $this->get('/secret')->assertOk();
    $this->get('/secret')->assertOk();

    Http::assertSentCount(1);
});

it('signs the user out when an administrator ended the session', function () {
    Event::fake([SsoSessionEnded::class]);
    Http::fake(['portal.test/*' => Http::response(['allowed' => false, 'sessionValid' => false, 'reason' => 'revoked'])]);

    signInThroughPortal();
    $this->travel(2)->minutes();

    $this->get('/secret')->assertRedirect(route('sso.ended'));

    expect(Auth::check())->toBeFalse();

    Event::assertDispatched(SsoSessionEnded::class, fn ($event) => $event->reason === 'revoked');
});

it('signs the user out when their access was removed', function () {
    Http::fake(['portal.test/*' => Http::response(['allowed' => false, 'sessionValid' => true, 'reason' => 'department_not_allowed'])]);

    signInThroughPortal();
    $this->travel(2)->minutes();

    $this->get('/secret')->assertRedirect(route('sso.ended'));
});

it('answers a Livewire or JSON request of an ended session with 401', function () {
    Http::fake(['portal.test/*' => Http::response(['allowed' => false, 'sessionValid' => false, 'reason' => 'revoked'])]);

    signInThroughPortal();
    $this->travel(2)->minutes();

    $this->getJson('/secret')->assertUnauthorized()->assertJson(['sessionExpired' => true, 'reason' => 'revoked']);
});

it('signs the user out at the portal deadline without asking the portal', function () {
    Http::fake();

    signInThroughPortal(['sexp' => time() + 600]);
    $this->travel(11)->minutes();

    $this->get('/secret')->assertRedirect(route('sso.ended'));

    Http::assertNothingSent();
});

it('keeps the user in when the portal cannot be reached', function () {
    Http::fake(['portal.test/*' => Http::response('down', 503)]);

    signInThroughPortal();
    $this->travel(2)->minutes();

    $this->get('/secret')->assertOk();
});

it('keeps the user in when the portal answers with something else than JSON', function () {
    Http::fake(['portal.test/*' => Http::response('<html>bad gateway</html>', 200)]);

    signInThroughPortal();
    $this->travel(2)->minutes();

    $this->get('/secret')->assertOk();
});

it('leaves a user who signed in locally alone', function () {
    Http::fake();

    $this->actingAs(User::query()->forceCreate(['name' => 'Local', 'email' => 'local@example.com', 'password' => 'x']));

    $this->get('/secret')->assertOk();

    Http::assertNothingSent();
    expect(Sso::active())->toBeFalse();
});

it('remembers that a user signed out of a portal session', function () {
    signInThroughPortal();

    Auth::logout();

    expect(Sso::signedOut())->toBeTrue();
});

it('does not mark a local sign-out as a portal sign-out', function () {
    $this->actingAs(User::query()->forceCreate(['name' => 'Local', 'email' => 'local@example.com', 'password' => 'x']));

    Auth::logout();

    expect(Sso::signedOut())->toBeFalse();
});

it('signs out through the logout route and returns to the portal', function () {
    signInThroughPortal();

    $this->post('/sso/logout')->assertRedirect('https://portal.example.com');

    expect(Auth::check())->toBeFalse();
});

it('sends a guest to the portal sign-in, or answers a JSON request with 401', function () {
    $this->get('/secret')->assertRedirect(route('sso.login'));
    $this->getJson('/secret')->assertUnauthorized()->assertJsonPath('loginUrl', 'https://sso.example.com/auth/login?app=inventory');
});

it('sends an ended session to the launchpad', function () {
    $this->get('/sso/ended')->assertRedirect('https://portal.example.com');
});

it('renders the browser guard only for a portal session', function () {
    expect(view('postiefs-sso::session-guard')->render())->not->toContain('<script>');

    signInThroughPortal(['sexp' => time() + 3600]);

    expect(view('postiefs-sso::session-guard')->render())->toContain('<script>')->toContain(addcslashes(route('sso.heartbeat'), '/'));
});

it('reports a rejected check secret in the log, and stays signed in', function () {
    Http::fake(['portal.test/*' => Http::response(['error' => 'invalid check token'], 401)]);

    signInThroughPortal();
    $this->travel(2)->minutes();

    $this->get('/secret')->assertOk();
});

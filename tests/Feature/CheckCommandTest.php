<?php

use Illuminate\Support\Facades\Http;

it('passes when the portal answers and accepts the secret', function () {
    Http::fake(['portal.test/*' => Http::response(['allowed' => false, 'sessionValid' => false, 'reason' => 'unknown_app'])]);

    $this->artisan('sso:check')
        ->expectsOutputToContain('SSO_JWT_SECRET matches the portal')
        ->assertSuccessful();
});

it('fails when the portal rejects the secret', function () {
    Http::fake(['portal.test/*' => Http::response(['error' => 'invalid check token'], 401)]);

    $this->artisan('sso:check')->assertFailed();
});

it('fails when single sign-on is not configured', function () {
    config(['postiefs-sso.secret' => null]);

    $this->artisan('sso:check')->assertFailed();
});

it('prints the callback url to register', function () {
    $this->artisan('sso:install')->expectsOutputToContain('/sso/callback')->assertSuccessful();
});

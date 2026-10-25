<?php

namespace Postiefs\Sso\Tests;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use Postiefs\Sso\SsoServiceProvider;
use Postiefs\Sso\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    public const SECRET = 'a-shared-secret-of-at-least-32-bytes!!';

    protected function getPackageProviders($app): array
    {
        return [SsoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');

        $app['config']->set('postiefs-sso.app_id', 'inventory');
        $app['config']->set('postiefs-sso.secret', self::SECRET);
        $app['config']->set('postiefs-sso.url', 'http://portal.test');
        $app['config']->set('postiefs-sso.public_url', 'https://sso.example.com');
        $app['config']->set('postiefs-sso.portal_url', 'https://portal.example.com');
        $app['config']->set('postiefs-sso.user.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'sso'])->get('/secret', fn () => 'secret page')->name('secret');
        Route::middleware(['web'])->get('/', fn () => 'home')->name('home');
    }
}

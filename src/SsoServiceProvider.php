<?php

namespace Postiefs\Sso;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Postiefs\Sso\Console\CheckCommand;
use Postiefs\Sso\Console\InstallCommand;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Contracts\UserResolver;
use Postiefs\Sso\Http\Middleware\Authenticate;
use Postiefs\Sso\Http\Middleware\EnsureSsoSession;
use Postiefs\Sso\Http\Middleware\HandleStrayToken;
use Postiefs\Sso\Listeners\RememberSignOut;
use Postiefs\Sso\Support\DefaultUserResolver;
use Postiefs\Sso\Support\HomeUrl;
use Postiefs\Sso\Support\SsoLog;

class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/postiefs-sso.php', 'postiefs-sso');

        $this->app->singleton(HomeUrl::class);

        $this->app->singleton(SsoLog::class, fn ($app) => new SsoLog(fn () => (array) $app['config']['postiefs-sso.log']));

        $this->app->singleton(PortalClient::class, fn ($app) => new SsoClient(fn () => (array) $app['config']['postiefs-sso'], $app->make(SsoLog::class)));

        // Scoped: one per request (Octane safe), always reading the current session.
        $this->app->scoped(SsoSession::class, fn ($app) => new SsoSession($app['session.store'], $app['config']['postiefs-sso.session_key']));

        $this->app->bind(UserResolver::class, fn ($app) => $app->make($app['config']['postiefs-sso.user.resolver'] ?? DefaultUserResolver::class));
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'postiefs-sso');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'postiefs-sso');

        if (config('postiefs-sso.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/sso.php');
        }

        $router->aliasMiddleware('sso.auth', Authenticate::class);
        $router->aliasMiddleware('sso.session', EnsureSsoSession::class);
        // `sso` = sign in through the portal and enforce its session rules.
        $router->middlewareGroup('sso', [Authenticate::class, EnsureSsoSession::class]);

        $this->registerStrayTokenHandling();

        Event::listen(Logout::class, RememberSignOut::class);

        // @ssoSessionGuard: drop it into a layout (apps without Filament).
        Blade::directive('ssoSessionGuard', fn () => "<?php echo view('postiefs-sso::session-guard')->render(); ?>");

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/postiefs-sso.php' => config_path('postiefs-sso.php')], 'postiefs-sso-config');

            $this->publishes([
                __DIR__.'/../database/migrations/add_sso_columns_to_users_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_add_sso_columns_to_users_table.php'),
            ], 'postiefs-sso-migrations');

            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/postiefs-sso')], 'postiefs-sso-views');

            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/postiefs-sso')], 'postiefs-sso-lang');

            $this->commands([InstallCommand::class, CheckCommand::class]);
        }
    }

    /**
     * Global, so it sees the request whichever page the portal sent the browser to, including a panel's own routes.
     */
    private function registerStrayTokenHandling(): void
    {
        if (! config('postiefs-sso.stray_token.detect', true)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(HandleStrayToken::class);
        }
    }
}

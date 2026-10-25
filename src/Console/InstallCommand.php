<?php

namespace Postiefs\Sso\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'sso:install';

    protected $description = 'Publish the SSO config and print the .env keys and the Callback URL to register in the portal';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'postiefs-sso-config']);

        $this->newLine();
        $this->info('Add these to .env (the values come from the SSO team):');
        $this->line(<<<'ENV'
SSO_APP_ID=
SSO_JWT_SECRET=
SSO_URL=
SSO_PUBLIC_URL=
SSO_PORTAL_URL=
SESSION_LIFETIME=720
ENV);

        $this->newLine();
        $this->info('Register this Callback URL for the app in the portal, exactly:');
        $this->line('  '.url(trim((string) config('postiefs-sso.routes.prefix', 'sso'), '/').'/callback'));
        $this->newLine();
        $this->line('Then run `php artisan sso:check` to test the connection.');

        return self::SUCCESS;
    }
}

<?php

namespace Postiefs\Sso\Console;

use Firebase\JWT\JWT;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Postiefs\Sso\Contracts\PortalClient;
use Throwable;

class CheckCommand extends Command
{
    protected $signature = 'sso:check';

    protected $description = 'Check the SSO settings and the connection to the portal';

    public function handle(PortalClient $portal): int
    {
        $ok = true;

        foreach ([
            'SSO_APP_ID' => config('postiefs-sso.app_id'),
            'SSO_JWT_SECRET' => config('postiefs-sso.secret'),
            'SSO_URL' => config('postiefs-sso.url'),
        ] as $name => $value) {
            $ok = $this->line_($name.' is set', filled($value)) && $ok;
        }

        $this->line_('SSO_PUBLIC_URL is set (the browser sign-in address)', filled(config('postiefs-sso.public_url')));
        $this->line_('SESSION_LIFETIME covers a long portal session (720 or more)', (int) config('session.lifetime') >= 720, warn: true);

        if (! $portal->enabled()) {
            $this->error('Single sign-on is off until SSO_APP_ID and SSO_JWT_SECRET are set.');

            return self::FAILURE;
        }

        $this->line('  Sign-in address: '.$portal->loginUrl());
        $this->line('  Callback URL to register in the portal: '.url(trim((string) config('postiefs-sso.routes.prefix', 'sso'), '/').'/callback'));

        // A session check for nobody: the portal answers 401 when the secret differs, anything else proves the secret.
        try {
            $response = Http::timeout((int) config('postiefs-sso.timeout', 5))->acceptJson()->post(
                rtrim((string) config('postiefs-sso.url'), '/').'/auth/check-access',
                ['token' => JWT::encode(['email' => 'sso-check@invalid', 'app' => config('postiefs-sso.app_id'), 'iss' => config('postiefs-sso.check_issuer'), 'iat' => time(), 'exp' => time() + 60], (string) config('postiefs-sso.secret'), 'HS256')],
            );

            $ok = $this->line_('The portal answers at SSO_URL', $response->status() !== 0 && ! $response->serverError()) && $ok;
            $ok = $this->line_('SSO_JWT_SECRET matches the portal', $response->status() !== 401) && $ok;
        } catch (Throwable $e) {
            $this->line_('The portal answers at SSO_URL ('.$e->getMessage().')', false);

            $ok = false;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function line_(string $label, bool $passed, bool $warn = false): bool
    {
        $this->line(($passed ? '  <info>OK</info>   ' : ($warn ? '  <comment>WARN</comment> ' : '  <error>FAIL</error> ')).$label);

        return $passed || $warn;
    }
}

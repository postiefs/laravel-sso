<?php

namespace Postiefs\Sso;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Postiefs\Sso\Contracts\PortalClient;
use Postiefs\Sso\Data\AccessCheck;
use Postiefs\Sso\Data\SsoIdentity;
use Postiefs\Sso\Exceptions\InvalidSsoToken;
use Postiefs\Sso\Exceptions\SsoNotConfigured;
use Postiefs\Sso\Support\SsoLog;
use Throwable;

/**
 * Talks to the SSO portal: verifies the launch token and asks whether a session is still alive.
 */
class SsoClient implements PortalClient
{
    /**
     * @param  Closure(): array<string, mixed>  $config  Returns the settings on every call, so a change at runtime (a test, Octane) is seen.
     */
    public function __construct(private Closure $config, private SsoLog $log) {}

    public function enabled(): bool
    {
        return filled($this->setting('app_id') ?? null) && filled($this->setting('secret') ?? null);
    }

    public function verifyLaunchToken(string $token): SsoIdentity
    {
        $this->assertConfigured();

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = (int) ($this->setting('leeway') ?? 30);

        try {
            $claims = (array) JWT::decode($token, new Key((string) $this->setting('secret'), 'HS256'));
        } catch (Throwable $e) {
            throw new InvalidSsoToken($e->getMessage(), 0, $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        if (($claims['iss'] ?? null) !== ($this->setting('issuer') ?? 'sso-portal')) {
            throw new InvalidSsoToken('Unexpected issuer');
        }

        // The audience must be this app, or a token made for another app could be replayed here.
        if (! in_array($this->setting('app_id'), (array) ($claims['aud'] ?? []), true)) {
            throw new InvalidSsoToken('Token was issued for a different application');
        }

        if (blank($claims['email'] ?? null)) {
            throw new InvalidSsoToken('Token has no email');
        }

        return SsoIdentity::fromClaims($claims, (int) ($this->setting('fallback_hours') ?? 8));
    }

    public function checkAccess(string $email, ?string $sessionId): AccessCheck
    {
        $claims = [
            'email' => strtolower($email),
            'app' => $this->setting('app_id'),
            'iss' => $this->setting('check_issuer') ?? 'app-check',
            'iat' => time(),
            'exp' => time() + 60,
        ];

        if ($sessionId) {
            $claims['jti'] = $sessionId;
        }

        $url = rtrim((string) $this->setting('url'), '/').'/auth/check-access';

        try {
            $this->assertConfigured();

            $response = Http::timeout((int) ($this->setting('timeout') ?? 5))
                ->acceptJson()
                ->post($url, ['token' => JWT::encode($claims, (string) $this->setting('secret'), 'HS256')]);

            if ($response->status() === 401) {
                $this->log->error('the portal rejected the check token: SSO_JWT_SECRET does not match the portal');
            }

            $data = $response->json();

            if ($response->serverError() || ! is_array($data)) {
                throw new \RuntimeException('HTTP '.$response->status());
            }

            return new AccessCheck(
                valid: ($data['allowed'] ?? true) !== false && ($data['sessionValid'] ?? true) !== false,
                reason: (string) ($data['reason'] ?? 'active'),
                secondsRemaining: isset($data['secondsRemaining']) ? (int) $data['secondsRemaining'] : null,
                expiresAt: $data['expiresAt'] ?? null,
                degraded: (bool) ($data['degraded'] ?? false),
            );
        } catch (Throwable $e) {
            $this->log->warning("could not reach {$url}: ".$e->getMessage());

            return AccessCheck::degraded();
        }
    }

    public function loginUrl(): string
    {
        $base = rtrim((string) (($this->setting('public_url') ?? '') ?: ($this->setting('url') ?? '')), '/');

        return $base.'/auth/login?app='.rawurlencode((string) $this->setting('app_id'));
    }

    public function portalUrl(): string
    {
        return (string) (($this->setting('portal_url') ?? '') ?: '/');
    }

    private function setting(string $key): mixed
    {
        return ($this->config)()[$key] ?? null;
    }

    private function assertConfigured(): void
    {
        if (blank($this->setting('app_id') ?? null)) {
            throw SsoNotConfigured::missing('app_id', 'SSO_APP_ID');
        }

        if (blank($this->setting('secret') ?? null)) {
            throw SsoNotConfigured::missing('secret', 'SSO_JWT_SECRET');
        }
    }
}

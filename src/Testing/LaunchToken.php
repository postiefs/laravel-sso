<?php

namespace Postiefs\Sso\Testing;

use Firebase\JWT\JWT;

/**
 * Makes launch tokens like the portal's, for an app's own tests:
 *
 *     $this->get('/sso/callback?sso_token='.LaunchToken::make(['email' => 'jane@example.com']))
 */
final class LaunchToken
{
    /**
     * @param  array<string, mixed>  $claims  Overrides: any claim, or `secret` to sign with another secret.
     */
    public static function make(array $claims = []): string
    {
        $secret = (string) ($claims['secret'] ?? config('postiefs-sso.secret'));
        unset($claims['secret']);

        $now = time();

        return JWT::encode(array_merge([
            'iss' => config('postiefs-sso.issuer', 'sso-portal'),
            'aud' => config('postiefs-sso.app_id'),
            'sub' => 'oid-'.md5((string) ($claims['email'] ?? 'jane@example.com')),
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
            'roles' => [],
            'department' => '',
            'groups' => [],
            'jti' => bin2hex(random_bytes(8)),
            'sexp' => $now + 8 * 3600,
            'iat' => $now,
            'exp' => $now + 60,
        ], $claims), $secret, 'HS256');
    }
}

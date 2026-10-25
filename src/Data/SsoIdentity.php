<?php

namespace Postiefs\Sso\Data;

/**
 * The identity a verified launch token carries.
 */
final readonly class SsoIdentity
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $groups
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public string $email,
        public string $name,
        public ?string $sub,
        public array $roles,
        public string $department,
        public array $groups,
        public ?string $sessionId,
        public int $expiresAt,
        public array $claims = [],
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims, int $fallbackHours = 8): self
    {
        $now = now()->getTimestamp();
        $deadline = (int) ($claims['sexp'] ?? 0);
        $email = strtolower(trim((string) $claims['email']));

        return new self(
            email: $email,
            name: (string) ($claims['name'] ?? '') !== '' ? (string) $claims['name'] : $email,
            sub: isset($claims['sub']) ? (string) $claims['sub'] : null,
            roles: array_values(array_map('strval', (array) ($claims['roles'] ?? []))),
            department: (string) ($claims['department'] ?? ''),
            groups: array_values(array_map('strval', (array) ($claims['groups'] ?? []))),
            sessionId: isset($claims['jti']) ? (string) $claims['jti'] : null,
            // The portal's absolute deadline is adopted as it is and never extended here.
            expiresAt: $deadline > $now ? $deadline : $now + $fallbackHours * 3600,
            claims: $claims,
        );
    }

    /**
     * A claim by the short names used in `postiefs-sso.user.attributes`.
     */
    public function claim(string $key): mixed
    {
        return match ($key) {
            'email' => $this->email,
            'name' => $this->name,
            'sub' => $this->sub,
            'roles' => $this->roles,
            'department' => $this->department,
            'groups' => $this->groups,
            'sid', 'jti' => $this->sessionId,
            default => $this->claims[$key] ?? null,
        };
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array('admin', $this->roles, true) || array_intersect($roles, $this->roles) !== [];
    }
}

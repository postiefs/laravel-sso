<?php

namespace Postiefs\Sso;

use Illuminate\Contracts\Session\Session;
use Postiefs\Sso\Data\SsoIdentity;

/**
 * The portal session kept in the Laravel session: when it ends, who it is for and what the portal said about them.
 * A user who signed in locally has none, and every rule of the package leaves them alone.
 *
 * Read it through the `Sso` facade: `Sso::active()`, `Sso::secondsRemaining()`, `Sso::hasRole('editor')`.
 */
class SsoSession
{
    private bool $signedOut = false;

    public function __construct(private Session $session, private string $key) {}

    public function start(SsoIdentity $identity): void
    {
        $this->signedOut = false;

        $this->session->put($this->key, [
            'sid' => $identity->sessionId,
            'exp' => $identity->expiresAt,
            'checked_at' => now()->getTimestamp(),
            'email' => $identity->email,
            'roles' => $identity->roles,
            'groups' => $identity->groups,
            'department' => $identity->department,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function data(): ?array
    {
        $data = $this->session->get($this->key);

        return is_array($data) ? $data : null;
    }

    public function active(): bool
    {
        return $this->data() !== null;
    }

    public function sessionId(): ?string
    {
        return $this->data()['sid'] ?? null;
    }

    public function expiresAt(): ?int
    {
        $expiresAt = $this->data()['exp'] ?? null;

        return $expiresAt === null ? null : (int) $expiresAt;
    }

    public function secondsRemaining(): ?int
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt === null ? null : max(0, $expiresAt - now()->getTimestamp());
    }

    public function expired(): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt !== null && now()->getTimestamp() >= $expiresAt;
    }

    public function dueForCheck(int $interval): bool
    {
        return now()->getTimestamp() - (int) ($this->data()['checked_at'] ?? 0) >= $interval;
    }

    public function markChecked(): void
    {
        if ($data = $this->data()) {
            $data['checked_at'] = now()->getTimestamp();
            $this->session->put($this->key, $data);
        }
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        return $this->data()['roles'] ?? [];
    }

    /**
     * True when the portal gave the user any of the roles. The portal's `admin` role always passes.
     */
    public function hasRole(string ...$roles): bool
    {
        $mine = $this->roles();

        return in_array('admin', $mine, true) || array_intersect($roles, $mine) !== [];
    }

    /**
     * @return array<int, string>
     */
    public function groups(): array
    {
        return $this->data()['groups'] ?? [];
    }

    public function department(): string
    {
        return (string) ($this->data()['department'] ?? '');
    }

    /**
     * Remember in this request that the user chose to sign out of a portal session, because the session is wiped
     * before the response that sends them to the portal is built.
     */
    public function markSignedOut(): void
    {
        $this->signedOut = true;
    }

    public function signedOut(): bool
    {
        return $this->signedOut;
    }

    public function forget(): void
    {
        $this->session->forget($this->key);
    }
}

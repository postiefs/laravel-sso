<?php

namespace Postiefs\Sso\Data;

/**
 * The portal's answer to "is this session alive and the user still allowed".
 */
final readonly class AccessCheck
{
    public function __construct(
        public bool $valid,
        public string $reason = 'active',
        public ?int $secondsRemaining = null,
        public ?string $expiresAt = null,
        public bool $degraded = false,
    ) {}

    /**
     * The answer used when the portal cannot be asked.
     */
    public static function degraded(): self
    {
        return new self(valid: true, reason: 'degraded', degraded: true);
    }
}

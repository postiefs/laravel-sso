<?php

namespace Postiefs\Sso\Exceptions;

use RuntimeException;

class SsoNotConfigured extends RuntimeException
{
    public static function missing(string $key, string $env): self
    {
        return new self("Single sign-on is not configured: sso.{$key} is empty (set {$env}).");
    }
}

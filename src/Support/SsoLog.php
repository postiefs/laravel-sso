<?php

namespace Postiefs\Sso\Support;

use Closure;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Every `[sso]` log line goes through here, so the channel and the debug switch are set in one place.
 */
class SsoLog
{
    /**
     * @param  Closure(): array<string, mixed>  $config  Returns the `log` settings on every call.
     */
    public function __construct(private Closure $config) {}

    /**
     * Only written while `postiefs-sso.log.debug` is on.
     *
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->setting('debug') ?? false) {
            $this->logger()->info('[sso] '.$message, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger()->info('[sso] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger()->warning('[sso] '.$message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger()->error('[sso] '.$message, $context);
    }

    private function setting(string $key): mixed
    {
        return ($this->config)()[$key] ?? null;
    }

    private function logger(): LoggerInterface
    {
        return filled($this->setting('channel') ?? null) ? Log::channel($this->setting('channel')) : Log::getFacadeRoot();
    }
}

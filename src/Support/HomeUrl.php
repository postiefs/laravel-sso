<?php

namespace Postiefs\Sso\Support;

use Closure;

/**
 * Where a user goes after signing in when there is no "intended" page: `postiefs-sso.home`, else what an integration (the
 * Filament package) registered, else "/".
 */
class HomeUrl
{
    private ?Closure $resolver = null;

    public function resolveUsing(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function get(): string
    {
        if (filled($home = config('postiefs-sso.home'))) {
            return (string) $home;
        }

        if ($this->resolver !== null && filled($url = ($this->resolver)())) {
            return (string) $url;
        }

        return url('/');
    }
}

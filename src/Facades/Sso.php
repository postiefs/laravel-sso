<?php

namespace Postiefs\Sso\Facades;

use Illuminate\Support\Facades\Facade;
use Postiefs\Sso\SsoSession;

/**
 * @method static bool active()
 * @method static ?string sessionId()
 * @method static ?int expiresAt()
 * @method static ?int secondsRemaining()
 * @method static bool expired()
 * @method static array roles()
 * @method static bool hasRole(string ...$roles)
 * @method static array groups()
 * @method static string department()
 * @method static bool signedOut()
 *
 * @see SsoSession
 */
class Sso extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SsoSession::class;
    }
}

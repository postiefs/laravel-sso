<?php

namespace Postiefs\Sso\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Why a sign-in through the portal was refused. An HTTP exception, so Laravel renders its error page with the status.
 */
class SignInFailed extends HttpException
{
    public static function notEnabled(): self
    {
        return new self(404, trans('postiefs-sso::messages.not_enabled'));
    }

    public static function refusedByPortal(string $reason): self
    {
        return new self(403, trans('postiefs-sso::messages.refused_by_portal', ['reason' => mb_substr($reason, 0, 100)]));
    }

    public static function missingToken(): self
    {
        return new self(400, trans('postiefs-sso::messages.missing_token'));
    }

    public static function invalidToken(): self
    {
        return new self(401, trans('postiefs-sso::messages.invalid_token'));
    }

    public static function tokenAlreadyUsed(): self
    {
        return new self(401, trans('postiefs-sso::messages.token_used'));
    }

    public static function userNotAllowed(): self
    {
        return new self(403, trans('postiefs-sso::messages.user_not_allowed'));
    }
}

<?php

namespace Postiefs\Sso\Exceptions;

use RuntimeException;

/**
 * A launch token that cannot be trusted: bad signature, expired, wrong issuer or audience, no email.
 */
class InvalidSsoToken extends RuntimeException {}

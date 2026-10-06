<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Exceptions;

/**
 * Keycloak token refused: invalid signature, dates, issuer or content.
 */
class InvalidTokenException extends KeycloakException
{
}

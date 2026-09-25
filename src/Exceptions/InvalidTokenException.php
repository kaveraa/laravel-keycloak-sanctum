<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Exceptions;

/**
 * Jeton Keycloak refusé : signature, dates, émetteur ou contenu invalides.
 */
class InvalidTokenException extends KeycloakException
{
}

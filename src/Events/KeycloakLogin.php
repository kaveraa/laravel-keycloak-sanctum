<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * A user has just logged in with Keycloak (before the code is exchanged for the token).
 */
final class KeycloakLogin
{
    /**
     * @param list<string>         $roles  application roles
     * @param array<string, mixed> $claims Keycloak information
     */
    public function __construct(
        public readonly Model $user,
        public readonly array $roles,
        public readonly array $claims,
    ) {
    }
}

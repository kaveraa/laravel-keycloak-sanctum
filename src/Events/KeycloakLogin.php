<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Un utilisateur vient de se connecter avec Keycloak (avant l'échange du code contre le jeton).
 */
final class KeycloakLogin
{
    /**
     * @param list<string>         $roles  rôles de l'application
     * @param array<string, mixed> $claims informations Keycloak
     */
    public function __construct(
        public readonly Model $user,
        public readonly array $roles,
        public readonly array $claims,
    ) {
    }
}

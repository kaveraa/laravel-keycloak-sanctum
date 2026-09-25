<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Events;

/**
 * Keycloak a demandé la fin d'une session (back-channel logout) : les jetons liés ont été supprimés.
 */
final class KeycloakLogout
{
    public function __construct(
        public readonly ?string $sid,
        public readonly ?string $sub,
        public readonly int $revokedTokens,
    ) {
    }
}

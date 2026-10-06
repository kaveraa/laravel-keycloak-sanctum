<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Events;

/**
 * Keycloak asked to end a session (back-channel logout): the linked tokens have been deleted.
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

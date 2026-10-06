<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Contracts;

/**
 * Implement this on the User model to save the roles on every login
 * (roles table, role column, permissions package...).
 */
interface SyncsKeycloakRoles
{
    /**
     * @param list<string> $roles application roles (after mapping)
     */
    public function syncKeycloakRoles(array $roles): void;
}

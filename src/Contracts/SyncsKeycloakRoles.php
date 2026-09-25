<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Contracts;

/**
 * À implémenter sur le modèle User pour enregistrer les rôles à chaque connexion
 * (table de rôles, colonne role, paquet de permissions...).
 */
interface SyncsKeycloakRoles
{
    /**
     * @param list<string> $roles rôles de l'application (après transformation)
     */
    public function syncKeycloakRoles(array $roles): void;
}

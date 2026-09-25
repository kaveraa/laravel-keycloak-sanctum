<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

/**
 * Transforme les rôles lus dans le jeton Keycloak en rôles de l'application.
 */
class RoleResolver
{
    /**
     * @param array<string, mixed> $config configuration keycloak-sanctum.roles
     */
    public function __construct(
        private readonly array $config,
        private readonly string $clientId,
    ) {
    }

    /**
     * @param array<string, mixed> $claims contenu du jeton d'accès Keycloak
     *
     * @return list<string> rôles de l'application, sans doublon
     */
    public function map(array $claims): array
    {
        $keycloakRoles = $this->keycloakRoles($claims);

        if (KeycloakSanctum::$mapRolesCallback !== null) {
            $roles = (KeycloakSanctum::$mapRolesCallback)($keycloakRoles, $claims);
        } else {
            $map = (array) ($this->config['map'] ?? []);
            $roles = $map === []
                ? $keycloakRoles
                : array_map(fn (string $role) => (string) $map[$role], array_values(array_filter(
                    $keycloakRoles,
                    fn (string $role) => array_key_exists($role, $map),
                )));
        }

        $roles = array_values(array_unique(array_map('strval', $roles)));

        $default = $this->config['default'] ?? null;
        if ($roles === [] && is_string($default) && $default !== '') {
            $roles = [$default];
        }

        return $roles;
    }

    /**
     * Rôles bruts du jeton, selon la source configurée (realm, client ou les deux).
     *
     * @param array<string, mixed> $claims
     *
     * @return list<string>
     */
    public function keycloakRoles(array $claims): array
    {
        $source = (string) ($this->config['source'] ?? 'client');
        $client = (string) ($this->config['client'] ?? '') ?: $this->clientId;

        $realmRoles = (array) ($claims['realm_access']['roles'] ?? []);
        $clientRoles = (array) ($claims['resource_access'][$client]['roles'] ?? []);

        $roles = match ($source) {
            'realm' => $realmRoles,
            'client' => $clientRoles,
            'both' => [...$realmRoles, ...$clientRoles],
            default => throw new \InvalidArgumentException("keycloak-sanctum.roles.source invalide : {$source} (attendu : realm, client ou both)"),
        };

        return array_values(array_unique(array_map('strval', $roles)));
    }
}

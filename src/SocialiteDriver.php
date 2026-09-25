<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

/**
 * Prépare le pilote Socialite "keycloak" à partir de la configuration du paquet.
 */
class SocialiteDriver
{
    /**
     * @param array<string, mixed> $config configuration keycloak-sanctum
     */
    public function __construct(private readonly array $config)
    {
    }

    public function make(): Provider
    {
        // Les valeurs déjà présentes dans config/services.php restent prioritaires
        $services = (array) config('services.keycloak', []);
        config(['services.keycloak' => $services + [
            'client_id' => $this->config['client_id'] ?? null,
            'client_secret' => $this->config['client_secret'] ?? null,
            'redirect' => $this->redirectUri(),
            'base_url' => $this->config['base_url'] ?? null,
            'realms' => $this->config['realm'] ?? null,
        ]]);

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver('keycloak');

        return $driver->scopes((array) ($this->config['scopes'] ?? ['openid']));
    }

    public function redirectUri(): string
    {
        $uri = $this->config['redirect_uri'] ?? null;

        return is_string($uri) && $uri !== '' ? $uri : route('keycloak-sanctum.callback');
    }
}

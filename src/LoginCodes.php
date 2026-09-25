<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * Codes à usage unique transmis au front après la connexion Keycloak.
 * Le front échange le code contre un jeton Sanctum : le jeton n'apparaît jamais dans une URL.
 */
class LoginCodes
{
    public function __construct(
        private readonly Cache $cache,
        private readonly int $ttl,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): string
    {
        $code = Str::random(64);
        $this->cache->put($this->key($code), $payload, $this->ttl);

        return $code;
    }

    /**
     * Retourne les données du code et le supprime (un code ne sert qu'une fois).
     *
     * @return array<string, mixed>|null null si le code est inconnu, déjà utilisé ou expiré
     */
    public function consume(string $code): ?array
    {
        $payload = $this->cache->pull($this->key($code));

        return is_array($payload) ? $payload : null;
    }

    private function key(string $code): string
    {
        // Seule une empreinte du code est stockée
        return 'keycloak-sanctum:code:'.hash('sha256', $code);
    }
}

<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * One-time codes sent to the frontend after the Keycloak login.
 * The frontend exchanges the code for a Sanctum token: the token never appears in a URL.
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
     * Returns the data of the code and deletes it (a code is used only once).
     *
     * @return array<string, mixed>|null null if the code is unknown, already used or expired
     */
    public function consume(string $code): ?array
    {
        $payload = $this->cache->pull($this->key($code));

        return is_array($payload) ? $payload : null;
    }

    private function key(string $code): string
    {
        // Only a hash of the code is stored
        return 'keycloak-sanctum:code:'.hash('sha256', $code);
    }
}

<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Kaveraa\KeycloakSanctum\Exceptions\InvalidTokenException;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;

/**
 * Dialogue avec le serveur Keycloak (OpenID Connect) : configuration, clés publiques,
 * vérification des jetons et adresse de déconnexion.
 */
class KeycloakClient
{
    /**
     * @param array<string, mixed> $config configuration keycloak-sanctum
     */
    public function __construct(
        private readonly array $config,
        private readonly Cache $cache,
    ) {
    }

    /**
     * Adresse du realm, qui est aussi l'émetteur ("iss") des jetons.
     */
    public function issuer(): string
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $realm = (string) ($this->config['realm'] ?? '');

        if ($baseUrl === '' || $realm === '') {
            throw new KeycloakException('KEYCLOAK_BASE_URL et KEYCLOAK_REALM doivent être configurés.');
        }

        return $baseUrl.'/realms/'.$realm;
    }

    public function clientId(): string
    {
        return (string) ($this->config['client_id'] ?? '');
    }

    /**
     * Configuration OpenID du realm (.well-known/openid-configuration), gardée en cache.
     *
     * @return array<string, mixed>
     */
    public function discovery(): array
    {
        return $this->cache->remember($this->cacheKey('discovery'), $this->cacheTtl(), function (): array {
            return $this->getJson($this->issuer().'/.well-known/openid-configuration');
        });
    }

    /**
     * Vérifie un jeton signé par Keycloak (signature, dates, émetteur) et retourne son contenu.
     * Si le jeton utilise une clé inconnue, les clés sont rechargées une fois (rotation des clés).
     *
     * @return array<string, mixed>
     *
     * @throws InvalidTokenException
     */
    public function verify(string $jwt): array
    {
        $kid = $this->keyId($jwt);
        $keys = $this->signingKeys();

        // Clé inconnue : Keycloak a peut-être changé de clés. Le rechargement est limité à une fois
        // par minute, pour qu'un envoi de faux jetons ne fasse pas interroger Keycloak en boucle.
        if ($kid !== null && ! array_key_exists($kid, $keys) && $this->cache->add($this->cacheKey('jwks-refresh'), true, 60)) {
            $keys = $this->signingKeys(refresh: true);
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = (int) ($this->config['leeway'] ?? 30);

        try {
            $claims = (array) json_decode((string) json_encode(JWT::decode($jwt, $keys)), true);
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Jeton invalide : '.$e->getMessage(), previous: $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        if (($claims['iss'] ?? null) !== $this->issuer()) {
            throw new InvalidTokenException('Jeton invalide : émetteur (iss) inattendu.');
        }

        return $claims;
    }

    /**
     * Adresse de déconnexion Keycloak (RP-initiated logout), ou null si Keycloak ne la fournit pas.
     */
    public function logoutUrl(?string $idToken, ?string $postLogoutRedirectUri): ?string
    {
        $endpoint = $this->discovery()['end_session_endpoint'] ?? null;
        if (! is_string($endpoint) || $endpoint === '') {
            return null;
        }

        $query = array_filter([
            'client_id' => $this->clientId(),
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
        ], fn ($value) => $value !== null && $value !== '');

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query($query);
    }

    /**
     * Clés publiques de signature du realm, indexées par identifiant (kid).
     *
     * @return array<string, \Firebase\JWT\Key>
     */
    private function signingKeys(bool $refresh = false): array
    {
        $cacheKey = $this->cacheKey('jwks');
        if ($refresh) {
            $this->cache->forget($cacheKey);
        }

        $jwks = $this->cache->remember($cacheKey, $this->cacheTtl(), function (): array {
            $uri = $this->discovery()['jwks_uri'] ?? null;
            if (! is_string($uri)) {
                throw new KeycloakException('La configuration OpenID ne contient pas de jwks_uri.');
            }

            return $this->getJson($uri);
        });

        // Keycloak publie aussi des clés de chiffrement (use = enc) : seules les clés de signature servent ici
        $jwks['keys'] = array_values(array_filter(
            (array) ($jwks['keys'] ?? []),
            fn ($key) => is_array($key) && ($key['use'] ?? 'sig') === 'sig',
        ));

        return JWK::parseKeySet($jwks, 'RS256');
    }

    private function keyId(string $jwt): ?string
    {
        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $jwt)[0]), true);

        return is_array($header) && isset($header['kid']) ? (string) $header['kid'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $response = Http::timeout((int) ($this->config['http']['timeout'] ?? 5))
            ->withOptions(['verify' => $this->config['http']['verify'] ?? true])
            ->acceptJson()
            ->get($url);

        if (! $response->successful() || ! is_array($response->json())) {
            throw new KeycloakException("Réponse invalide de Keycloak ({$response->status()}) pour {$url}");
        }

        return $response->json();
    }

    private function cacheKey(string $name): string
    {
        return 'keycloak-sanctum:'.$name.':'.md5($this->issuer());
    }

    private function cacheTtl(): int
    {
        return (int) ($this->config['cache']['ttl'] ?? 3600);
    }
}

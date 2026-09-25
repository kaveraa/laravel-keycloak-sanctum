<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Faux serveur Keycloak pour les tests : vraies clés RSA, vrais jetons signés,
 * configuration OpenID et clés publiques servies via Http::fake().
 */
final class FakeKeycloak
{
    public const BASE_URL = 'https://sso.example.test';

    public const REALM = 'demo';

    public const CLIENT_ID = 'demo-app';

    public const ISSUER = self::BASE_URL.'/realms/'.self::REALM;

    /** @var array<string, \OpenSSLAsymmetricKey> */
    private static array $keys = [];

    /**
     * Clé privée RSA pour un identifiant de clé (kid), générée une fois par test.
     */
    public static function privateKey(string $kid = 'key-1'): \OpenSSLAsymmetricKey
    {
        return self::$keys[$kid] ??= openssl_pkey_new(self::opensslOptions() + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA])
            ?: throw new \RuntimeException('Impossible de générer une clé RSA : '.openssl_error_string());
    }

    /**
     * Sous Windows, OpenSSL a besoin du chemin de openssl.cnf (livré avec PHP dans extras/ssl).
     *
     * @return array{config?: string}
     */
    private static function opensslOptions(): array
    {
        if (PHP_OS_FAMILY !== 'Windows' || getenv('OPENSSL_CONF')) {
            return [];
        }

        $config = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        return is_file($config) ? ['config' => $config] : [];
    }

    /**
     * Clés publiques au format JWKS, comme les publie Keycloak (avec une clé de chiffrement à ignorer).
     *
     * @param list<string> $kids
     *
     * @return array{keys: list<array<string, string>>}
     */
    public static function jwks(array $kids = ['key-1']): array
    {
        $keys = [];
        foreach ($kids as $kid) {
            $details = openssl_pkey_get_details(self::privateKey($kid));
            $keys[] = [
                'kid' => $kid,
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
            ];
        }

        $keys[] = ['kid' => 'enc-key', 'kty' => 'RSA', 'alg' => 'RSA-OAEP', 'use' => 'enc', 'n' => 'AQAB', 'e' => 'AQAB'];

        return ['keys' => $keys];
    }

    /**
     * Sert la configuration OpenID et les clés publiques.
     * Chaque appel à jwks_uri retourne l'élément suivant de $jwksSequence (rotation de clés).
     *
     * @param list<list<string>> $jwksSequence
     */
    public static function fake(array $jwksSequence = [['key-1']]): void
    {
        // Repart d'une simulation vierge : les Http::fake() successifs s'additionnent
        Http::swap(new Factory());

        $sequence = Http::sequence();
        foreach ($jwksSequence as $kids) {
            $sequence->push(self::jwks($kids));
        }
        // Une fois la séquence terminée, Keycloak continue de servir les dernières clés
        $sequence->whenEmpty(Http::response(self::jwks(end($jwksSequence))));

        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'jwks_uri' => self::ISSUER.'/protocol/openid-connect/certs',
                'end_session_endpoint' => self::ISSUER.'/protocol/openid-connect/logout',
            ]),
            self::ISSUER.'/protocol/openid-connect/certs' => $sequence,
        ]);
    }

    /**
     * Keycloak ne répond plus.
     */
    public static function down(): void
    {
        Http::swap(new Factory());
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);
    }

    /**
     * Jeton d'accès signé, avec des valeurs réalistes remplaçables par $claims.
     *
     * @param array<string, mixed> $claims
     */
    public static function accessToken(array $claims = [], string $kid = 'key-1'): string
    {
        return self::sign(array_merge([
            'iss' => self::ISSUER,
            'aud' => 'account',
            'azp' => self::CLIENT_ID,
            'sub' => 'user-123',
            'sid' => 'session-abc',
            'iat' => time(),
            'exp' => time() + 300,
            'preferred_username' => 'jdupont',
            'email' => 'jean.dupont@example.test',
            'name' => 'Jean Dupont',
            'realm_access' => ['roles' => ['offline_access']],
            'resource_access' => [self::CLIENT_ID => ['roles' => ['admin']]],
        ], $claims), $kid);
    }

    /**
     * Logout token de back-channel logout signé.
     *
     * @param array<string, mixed> $claims
     */
    public static function logoutToken(array $claims = [], string $kid = 'key-1'): string
    {
        return self::sign(array_merge([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'user-123',
            'sid' => 'session-abc',
            'iat' => time(),
            'jti' => bin2hex(random_bytes(8)),
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass()],
        ], $claims), $kid);
    }

    /**
     * @param array<string, mixed> $claims
     */
    public static function sign(array $claims, string $kid = 'key-1'): string
    {
        openssl_pkey_export(self::privateKey($kid), $pem, null, self::opensslOptions());

        return JWT::encode($claims, $pem, 'RS256', $kid);
    }

    public static function reset(): void
    {
        self::$keys = [];
    }
}

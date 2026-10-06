<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Fake Keycloak server for the tests: real RSA keys, real signed tokens,
 * OpenID configuration and public keys served through Http::fake().
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
     * RSA private key for a key id (kid), generated once per test.
     */
    public static function privateKey(string $kid = 'key-1'): \OpenSSLAsymmetricKey
    {
        return self::$keys[$kid] ??= openssl_pkey_new(self::opensslOptions() + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA])
            ?: throw new \RuntimeException('Impossible de générer une clé RSA : '.openssl_error_string());
    }

    /**
     * On Windows, OpenSSL needs the path of openssl.cnf (shipped with PHP in extras/ssl).
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
     * Public keys in JWKS format, as Keycloak publishes them (with an encryption key to ignore).
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
     * Serves the OpenID configuration and the public keys.
     * Each call to jwks_uri returns the next item of $jwksSequence (key rotation).
     *
     * @param list<list<string>> $jwksSequence
     */
    public static function fake(array $jwksSequence = [['key-1']]): void
    {
        // Starts from a clean fake: successive Http::fake() calls add up
        Http::swap(new Factory());

        $sequence = Http::sequence();
        foreach ($jwksSequence as $kids) {
            $sequence->push(self::jwks($kids));
        }
        // Once the sequence is over, Keycloak keeps serving the last keys
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
     * Keycloak no longer answers.
     */
    public static function down(): void
    {
        Http::swap(new Factory());
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);
    }

    /**
     * Signed access token, with realistic values that $claims can override.
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
     * Signed back-channel logout token.
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

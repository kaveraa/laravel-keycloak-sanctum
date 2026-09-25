<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Kaveraa\KeycloakSanctum\Exceptions\InvalidTokenException;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;
use Kaveraa\KeycloakSanctum\KeycloakClient;
use Kaveraa\KeycloakSanctum\Tests\Support\FakeKeycloak;
use Kaveraa\KeycloakSanctum\Tests\TestCase;

final class KeycloakClientTest extends TestCase
{
    private function client(): KeycloakClient
    {
        return $this->app->make(KeycloakClient::class);
    }

    public function test_verifie_un_jeton_valide(): void
    {
        $claims = $this->client()->verify(FakeKeycloak::accessToken(['sub' => 'abc']));

        self::assertSame('abc', $claims['sub']);
        self::assertSame(['admin'], $claims['resource_access'][FakeKeycloak::CLIENT_ID]['roles']);
    }

    public function test_refuse_un_autre_emetteur(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->client()->verify(FakeKeycloak::accessToken(['iss' => 'https://pirate.example.test/realms/demo']));
    }

    public function test_refuse_un_jeton_expire(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->client()->verify(FakeKeycloak::accessToken(['iat' => time() - 3600, 'exp' => time() - 600]));
    }

    public function test_tolere_un_petit_decalage_d_horloge(): void
    {
        $claims = $this->client()->verify(FakeKeycloak::accessToken(['exp' => time() - 10]));

        self::assertSame('user-123', $claims['sub']);
    }

    public function test_refuse_une_signature_invalide(): void
    {
        // Jeton signé par une autre clé mais qui annonce le kid de la clé connue
        $forged = FakeKeycloak::accessToken([], 'pirate');
        [, $payload, $signature] = explode('.', $forged);
        $header = explode('.', FakeKeycloak::accessToken())[0];

        $this->expectException(InvalidTokenException::class);

        $this->client()->verify($header.'.'.$payload.'.'.$signature);
    }

    public function test_recharge_les_cles_quand_keycloak_en_change(): void
    {
        FakeKeycloak::fake([['key-1'], ['key-1', 'key-2']]);
        $client = $this->client();

        $client->verify(FakeKeycloak::accessToken([], 'key-1'));
        $claims = $client->verify(FakeKeycloak::accessToken(['sub' => 'rotated'], 'key-2'));

        self::assertSame('rotated', $claims['sub']);
        Http::assertSentCount(3); // configuration + 2 lectures des clés
    }

    public function test_des_faux_jetons_ne_font_pas_interroger_keycloak_en_boucle(): void
    {
        $client = $this->client();

        foreach (['pirate-1', 'pirate-2', 'pirate-3'] as $kid) {
            try {
                $client->verify(FakeKeycloak::accessToken([], $kid));
                self::fail('Le jeton aurait dû être refusé');
            } catch (InvalidTokenException) {
            }
        }

        Http::assertSentCount(3); // configuration + clés + un seul rechargement
    }

    public function test_les_cles_sont_gardees_en_cache(): void
    {
        $client = $this->client();

        $client->verify(FakeKeycloak::accessToken());
        $client->verify(FakeKeycloak::accessToken());

        Http::assertSentCount(2); // configuration + clés, une seule fois
    }

    public function test_adresse_de_deconnexion(): void
    {
        $url = $this->client()->logoutUrl('id-token-xyz', 'https://front.example.test/');

        self::assertStringStartsWith(FakeKeycloak::ISSUER.'/protocol/openid-connect/logout?', $url);
        self::assertStringContainsString('id_token_hint=id-token-xyz', $url);
        self::assertStringContainsString('client_id='.FakeKeycloak::CLIENT_ID, $url);
        self::assertStringContainsString('post_logout_redirect_uri='.urlencode('https://front.example.test/'), $url);
    }

    public function test_configuration_manquante(): void
    {
        config(['keycloak-sanctum.base_url' => null]);
        $this->app->forgetInstance(KeycloakClient::class);

        $this->expectException(KeycloakException::class);

        $this->client()->issuer();
    }

    public function test_keycloak_injoignable(): void
    {
        FakeKeycloak::down();

        $this->expectException(KeycloakException::class);

        $this->client()->verify(FakeKeycloak::accessToken());
    }
}

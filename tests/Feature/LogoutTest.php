<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Kaveraa\KeycloakSanctum\Events\KeycloakLogout;
use Kaveraa\KeycloakSanctum\Models\KeycloakSession;
use Kaveraa\KeycloakSanctum\Tests\Support\FakeKeycloak;
use Kaveraa\KeycloakSanctum\Tests\TestCase;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['keycloak-sanctum.users.auto_create' => true]);
    }

    public function test_deconnexion_depuis_le_front(): void
    {
        $token = $this->loginWithKeycloak()->json('token');

        $response = $this->withToken($token)->postJson('/sso/logout')->assertOk();

        $url = (string) $response->json('logout_url');
        self::assertStringStartsWith(FakeKeycloak::ISSUER.'/protocol/openid-connect/logout?', $url);
        self::assertStringContainsString('id_token_hint=id-token-xyz', $url);

        self::assertSame(0, PersonalAccessToken::query()->count());
        self::assertSame(0, KeycloakSession::query()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/sso/user')->assertUnauthorized();
    }

    public function test_deconnexion_sans_jeton(): void
    {
        $this->postJson('/sso/logout')->assertUnauthorized();
    }

    public function test_backchannel_logout_par_session(): void
    {
        Event::fake([KeycloakLogout::class]);
        $this->loginWithKeycloak(['sid' => 'session-1'])->assertOk();
        $this->loginWithKeycloak(['sid' => 'session-2'])->assertOk();

        $this->post('/sso/backchannel-logout', ['logout_token' => FakeKeycloak::logoutToken(['sid' => 'session-1'])])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        self::assertSame(['session-2'], KeycloakSession::query()->pluck('sid')->all());
        self::assertSame(1, PersonalAccessToken::query()->count());
        Event::assertDispatched(KeycloakLogout::class, fn (KeycloakLogout $e) => $e->sid === 'session-1' && $e->revokedTokens === 1);
    }

    public function test_backchannel_logout_de_toutes_les_sessions_d_un_utilisateur(): void
    {
        $this->loginWithKeycloak(['sid' => 'session-1'])->assertOk();
        $this->loginWithKeycloak(['sid' => 'session-2'])->assertOk();

        $logoutToken = FakeKeycloak::logoutToken(['sid' => null]);
        // No sid: Keycloak asks to end all the sessions of the user (sub)
        $claims = json_decode(base64_decode(strtr(explode('.', $logoutToken)[1], '-_', '+/')), true);
        unset($claims['sid']);

        $this->post('/sso/backchannel-logout', ['logout_token' => FakeKeycloak::sign($claims)])->assertOk();

        self::assertSame(0, PersonalAccessToken::query()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1?: string}>
     */
    public static function invalidLogoutTokens(): array
    {
        return [
            'autre destinataire' => [['aud' => 'other-app']],
            'événement absent' => [['events' => ['other' => []]]],
            'nonce interdit' => [['nonce' => 'abc']],
            'autre émetteur' => [['iss' => 'https://pirate.example.test/realms/demo']],
            'mauvaise signature' => [[], 'pirate'],
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLogoutTokens')]
    public function test_logout_token_invalide(array $claims, string $kid = 'key-1'): void
    {
        $this->loginWithKeycloak()->assertOk();

        $this->post('/sso/backchannel-logout', ['logout_token' => FakeKeycloak::logoutToken($claims, $kid)])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request');

        self::assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_logout_token_absent(): void
    {
        $this->post('/sso/backchannel-logout')->assertStatus(400);
    }

    public function test_logout_token_rejoue(): void
    {
        $logoutToken = FakeKeycloak::logoutToken(['jti' => 'unique-id']);

        $this->post('/sso/backchannel-logout', ['logout_token' => $logoutToken])->assertOk();
        $this->post('/sso/backchannel-logout', ['logout_token' => $logoutToken])->assertStatus(400);
    }

    public function test_backchannel_logout_sans_csrf_ni_session(): void
    {
        // Route called by the Keycloak server: no cookie, no CSRF token
        $this->withoutMiddleware([])
            ->call('POST', '/sso/backchannel-logout', ['logout_token' => FakeKeycloak::logoutToken()])
            ->assertOk();
    }
}

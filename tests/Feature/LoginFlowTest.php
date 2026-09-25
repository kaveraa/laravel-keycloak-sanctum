<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Kaveraa\KeycloakSanctum\Events\KeycloakLogin;
use Kaveraa\KeycloakSanctum\KeycloakSanctum;
use Kaveraa\KeycloakSanctum\Models\KeycloakSession;
use Kaveraa\KeycloakSanctum\Tests\Support\FakeKeycloak;
use Kaveraa\KeycloakSanctum\Tests\Support\User;
use Kaveraa\KeycloakSanctum\Tests\TestCase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class LoginFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['keycloak-sanctum.users.auto_create' => true]);
    }

    public function test_login_redirige_vers_keycloak(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('scopes')->with(['openid', 'profile', 'email'])->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse(FakeKeycloak::ISSUER.'/protocol/openid-connect/auth?client_id=demo-app'));
        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $this->get('/sso/login')->assertRedirect(FakeKeycloak::ISSUER.'/protocol/openid-connect/auth?client_id=demo-app');

        self::assertSame(url('/sso/callback'), config('services.keycloak.redirect'));
        self::assertSame(FakeKeycloak::REALM, config('services.keycloak.realms'));
    }

    public function test_parcours_complet(): void
    {
        Event::fake([KeycloakLogin::class]);

        $response = $this->loginWithKeycloak()->assertOk();

        $response->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_at', null)
            ->assertJsonPath('user.name', 'Jean Dupont')
            ->assertJsonPath('user.roles', ['admin']);

        $user = User::query()->sole();
        self::assertSame('user-123', $user->keycloak_id);
        self::assertSame(['admin'], $user->roles, 'syncKeycloakRoles() a été appelé');

        $session = KeycloakSession::query()->sole();
        self::assertSame('session-abc', $session->sid);
        self::assertSame('user-123', $session->sub);
        self::assertSame('id-token-xyz', $session->id_token);
        self::assertNotSame('id-token-xyz', $session->getRawOriginal('id_token'), 'id_token chiffré en base');

        Event::assertDispatched(KeycloakLogin::class, fn (KeycloakLogin $e) => $e->user->is($user) && $e->roles === ['admin']);

        // Le jeton donne accès à l'API
        $this->withToken($response->json('token'))
            ->getJson('/sso/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'jean.dupont@example.test')
            ->assertJsonPath('user.roles', ['admin']);
    }

    public function test_le_code_ne_sert_qu_une_fois(): void
    {
        $this->fakeSocialiteUser(FakeKeycloak::accessToken());
        $location = $this->get('/sso/callback')->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->postJson('/sso/token', ['code' => $query['code']])->assertOk();
        $this->postJson('/sso/token', ['code' => $query['code']])->assertStatus(422)->assertJsonPath('error', 'invalid_code');
    }

    public function test_code_inconnu_ou_absent(): void
    {
        $this->postJson('/sso/token', ['code' => 'inconnu'])->assertStatus(422)->assertJsonPath('error', 'invalid_code');
        $this->postJson('/sso/token', [])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_le_code_expire(): void
    {
        $this->fakeSocialiteUser(FakeKeycloak::accessToken());
        $location = $this->get('/sso/callback')->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->travel(61)->seconds();

        $this->postJson('/sso/token', ['code' => $query['code']])->assertStatus(422);
    }

    public function test_redirection_vers_le_front_avec_le_code(): void
    {
        $this->fakeSocialiteUser(FakeKeycloak::accessToken());

        $location = (string) $this->get('/sso/callback')->headers->get('Location');

        self::assertMatchesRegularExpression('#^https://front\.example\.test/login/callback\?code=[A-Za-z0-9]{64}$#', $location);
    }

    public function test_connexion_annulee_dans_keycloak(): void
    {
        $this->get('/sso/callback?error=access_denied')
            ->assertRedirect('https://front.example.test/login/callback?error=access_denied');
    }

    public function test_echec_socialite(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('state invalide'));
        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $this->get('/sso/callback')->assertRedirect('https://front.example.test/login/callback?error=authentication_failed');
    }

    public function test_jeton_mal_signe(): void
    {
        $this->fakeSocialiteUser(FakeKeycloak::accessToken([], 'pirate'));

        $this->get('/sso/callback')->assertRedirect('https://front.example.test/login/callback?error=invalid_token');
        self::assertSame(0, User::query()->count());
    }

    public function test_jeton_delivre_pour_un_autre_client(): void
    {
        $this->fakeSocialiteUser(FakeKeycloak::accessToken(['azp' => 'other-app']));

        $this->get('/sso/callback')->assertRedirect('https://front.example.test/login/callback?error=invalid_token');
    }

    public function test_utilisateur_inconnu_sans_creation_automatique(): void
    {
        config(['keycloak-sanctum.users.auto_create' => false]);
        $this->fakeSocialiteUser(FakeKeycloak::accessToken());

        $this->get('/sso/callback')->assertRedirect('https://front.example.test/login/callback?error=user_not_found');
    }

    public function test_role_obligatoire(): void
    {
        config(['keycloak-sanctum.roles.required' => true]);
        $this->fakeSocialiteUser(FakeKeycloak::accessToken(['resource_access' => []]));

        $this->get('/sso/callback')->assertRedirect('https://front.example.test/login/callback?error=no_role');
    }

    public function test_front_sur_le_meme_domaine(): void
    {
        config(['keycloak-sanctum.frontend.callback_url' => '/login/callback?from=sso']);
        $this->fakeSocialiteUser(FakeKeycloak::accessToken());

        $location = (string) $this->get('/sso/callback')->headers->get('Location');

        self::assertStringStartsWith(url('/login/callback?from=sso&code='), $location);
    }

    public function test_expiration_du_jeton(): void
    {
        config(['keycloak-sanctum.token.expiration' => 120]);
        $this->freezeTime();

        $response = $this->loginWithKeycloak()->assertOk();

        $response->assertJsonPath('expires_at', now()->addMinutes(120)->toIso8601String());
    }

    public function test_donnees_utilisateur_personnalisees(): void
    {
        KeycloakSanctum::userPayloadUsing(fn ($user, array $roles) => ['id' => $user->id, 'is_admin' => in_array('admin', $roles, true)]);

        $this->loginWithKeycloak()->assertOk()->assertExactJsonStructure([
            'token', 'token_type', 'expires_at', 'user' => ['id', 'is_admin'],
        ])->assertJsonPath('user.is_admin', true);
    }

    public function test_settings(): void
    {
        $this->getJson('/sso/settings')
            ->assertOk()
            ->assertExactJson(['login_url' => url('/sso/login'), 'idle_timeout' => null]);
    }
}

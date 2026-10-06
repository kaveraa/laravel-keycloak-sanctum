<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kaveraa\KeycloakSanctum\KeycloakSanctum;
use Kaveraa\KeycloakSanctum\KeycloakSanctumServiceProvider;
use Kaveraa\KeycloakSanctum\Tests\Support\FakeKeycloak;
use Kaveraa\KeycloakSanctum\Tests\Support\User;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\SanctumServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use Laravel\Socialite\Two\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;
use Mockery;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            SocialiteServiceProvider::class,
            \SocialiteProviders\Manager\ServiceProvider::class,
            KeycloakSanctumServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('keycloak-sanctum.base_url', FakeKeycloak::BASE_URL);
        $app['config']->set('keycloak-sanctum.realm', FakeKeycloak::REALM);
        $app['config']->set('keycloak-sanctum.client_id', FakeKeycloak::CLIENT_ID);
        $app['config']->set('keycloak-sanctum.client_secret', 'secret');
        $app['config']->set('keycloak-sanctum.users.model', User::class);
        $app['config']->set('keycloak-sanctum.frontend.callback_url', 'https://front.example.test/login/callback');
        $app['config']->set('keycloak-sanctum.frontend.post_logout_redirect_uri', 'https://front.example.test/');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->json('roles')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(dirname(__DIR__).'/vendor/laravel/sanctum/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        FakeKeycloak::fake();
    }

    protected function tearDown(): void
    {
        KeycloakSanctum::reset();
        FakeKeycloak::reset();
        Sanctum::$accessTokenAuthenticationCallback = null;

        parent::tearDown();
    }

    /**
     * Simulates the return from Keycloak in Socialite with a signed access token.
     *
     * @param array<string, mixed> $raw profile information (userinfo)
     */
    protected function fakeSocialiteUser(string $accessToken, array $raw = [], ?string $idToken = 'id-token-xyz'): void
    {
        $user = (new SocialiteUser())
            ->setRaw($raw + ['sub' => 'user-123', 'email' => 'jean.dupont@example.test', 'name' => 'Jean Dupont'])
            ->setToken($accessToken)
            ->setAccessTokenResponseBody(array_filter(['access_token' => $accessToken, 'id_token' => $idToken]));

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($user);

        // once(): a second call to fakeSocialiteUser() takes over for the next login
        Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($provider);
    }

    /**
     * Full flow: return from Keycloak, then code exchange. Returns the JSON response of /sso/token.
     *
     * @param array<string, mixed> $claims
     */
    protected function loginWithKeycloak(array $claims = []): \Illuminate\Testing\TestResponse
    {
        $this->fakeSocialiteUser(FakeKeycloak::accessToken($claims));

        $redirect = $this->get('/sso/callback?code=kc&state=s')->assertRedirect();
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        return $this->postJson('/sso/token', ['code' => $query['code'] ?? '']);
    }
}

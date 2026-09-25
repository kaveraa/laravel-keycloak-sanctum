<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Feature;

use Kaveraa\KeycloakSanctum\Tests\Support\User;
use Kaveraa\KeycloakSanctum\Tests\TestCase;
use Laravel\Sanctum\PersonalAccessToken;

final class IdleTimeoutTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Doit être défini avant le démarrage du ServiceProvider
        $app['config']->set('keycloak-sanctum.token.idle_timeout', 30);
        $app['config']->set('keycloak-sanctum.users.auto_create', true);
    }

    public function test_jeton_actif(): void
    {
        $token = $this->loginWithKeycloak()->json('token');
        $this->travel(20)->minutes();

        $this->withToken($token)->getJson('/sso/user')->assertOk();
    }

    public function test_jeton_inactif_trop_longtemps(): void
    {
        $token = $this->loginWithKeycloak()->json('token');
        $this->travel(31)->minutes();

        $this->withToken($token)->getJson('/sso/user')->assertUnauthorized();
    }

    public function test_chaque_requete_prolonge_la_session(): void
    {
        $token = $this->loginWithKeycloak()->json('token');

        $this->travel(20)->minutes();
        $this->withToken($token)->getJson('/sso/user')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->travel(20)->minutes();
        $this->withToken($token)->getJson('/sso/user')->assertOk();
    }

    public function test_les_autres_jetons_ne_sont_pas_concernes(): void
    {
        $user = User::query()->forceCreate(['name' => 'Robot']);
        $token = $user->createToken('api-robot')->plainTextToken;
        PersonalAccessToken::query()->update(['last_used_at' => now()->subDays(2)]);

        $this->withToken($token)->getJson('/sso/user')->assertOk();
    }

    public function test_settings_indique_le_delai(): void
    {
        $this->getJson('/sso/settings')->assertJsonPath('idle_timeout', 30);
    }
}

<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Feature;

use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;
use Kaveraa\KeycloakSanctum\KeycloakSanctum;
use Kaveraa\KeycloakSanctum\Tests\Support\User;
use Kaveraa\KeycloakSanctum\Tests\TestCase;
use Kaveraa\KeycloakSanctum\UserResolver;

final class UserResolverTest extends TestCase
{
    private const CLAIMS = ['sub' => 'user-123', 'email' => 'jean.dupont@example.test', 'name' => 'Jean Dupont'];

    private function resolver(): UserResolver
    {
        return $this->app->make(UserResolver::class);
    }

    public function test_utilisateur_existant_mis_a_jour(): void
    {
        $existing = User::query()->forceCreate(['keycloak_id' => 'user-123', 'name' => 'Ancien nom']);

        $user = $this->resolver()->resolve(self::CLAIMS);

        self::assertTrue($user->is($existing));
        self::assertSame('Jean Dupont', $user->fresh()->name);
        self::assertSame('jean.dupont@example.test', $user->fresh()->email);
    }

    public function test_utilisateur_inconnu_refuse_par_defaut(): void
    {
        self::assertNull($this->resolver()->resolve(self::CLAIMS));
        self::assertSame(0, User::query()->count());
    }

    public function test_creation_automatique(): void
    {
        config(['keycloak-sanctum.users.auto_create' => true]);

        $user = $this->resolver()->resolve(self::CLAIMS);

        self::assertTrue($user->exists);
        self::assertSame('user-123', $user->keycloak_id);
        self::assertSame('Jean Dupont', $user->name);
    }

    public function test_identification_par_email(): void
    {
        config(['keycloak-sanctum.users.identifier' => ['column' => 'email', 'claim' => 'email']]);
        $existing = User::query()->forceCreate(['email' => 'jean.dupont@example.test']);

        self::assertTrue($this->resolver()->resolve(self::CLAIMS)->is($existing));
    }

    public function test_claim_d_identification_absent(): void
    {
        $this->expectException(KeycloakException::class);

        $this->resolver()->resolve(['email' => 'x@example.test']);
    }

    public function test_callback_personnalise(): void
    {
        $existing = User::query()->forceCreate(['name' => 'Choisi']);
        KeycloakSanctum::resolveUsersUsing(fn (array $claims) => User::query()->find($existing->id));

        self::assertTrue($this->resolver()->resolve(self::CLAIMS)->is($existing));
    }
}

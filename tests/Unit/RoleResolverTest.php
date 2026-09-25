<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Unit;

use Kaveraa\KeycloakSanctum\KeycloakSanctum;
use Kaveraa\KeycloakSanctum\RoleResolver;
use PHPUnit\Framework\TestCase;

final class RoleResolverTest extends TestCase
{
    private const CLAIMS = [
        'realm_access' => ['roles' => ['offline_access', 'staff']],
        'resource_access' => [
            'demo-app' => ['roles' => ['admin', 'reader']],
            'other-app' => ['roles' => ['other']],
        ],
    ];

    protected function tearDown(): void
    {
        KeycloakSanctum::reset();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolver(array $config = []): RoleResolver
    {
        return new RoleResolver($config + ['source' => 'client', 'map' => [], 'default' => null], 'demo-app');
    }

    public function test_roles_du_client_par_defaut(): void
    {
        self::assertSame(['admin', 'reader'], $this->resolver()->map(self::CLAIMS));
    }

    public function test_roles_du_realm(): void
    {
        self::assertSame(['offline_access', 'staff'], $this->resolver(['source' => 'realm'])->map(self::CLAIMS));
    }

    public function test_roles_du_realm_et_du_client(): void
    {
        self::assertSame(['offline_access', 'staff', 'admin', 'reader'], $this->resolver(['source' => 'both'])->map(self::CLAIMS));
    }

    public function test_autre_client(): void
    {
        self::assertSame(['other'], $this->resolver(['client' => 'other-app'])->map(self::CLAIMS));
    }

    public function test_table_de_correspondance_ne_garde_que_les_roles_listes(): void
    {
        $roles = $this->resolver(['source' => 'both', 'map' => ['admin' => 'administrator', 'staff' => 'member']])->map(self::CLAIMS);

        self::assertSame(['member', 'administrator'], $roles);
    }

    public function test_deux_roles_keycloak_vers_le_meme_role(): void
    {
        $roles = $this->resolver(['map' => ['admin' => 'manager', 'reader' => 'manager']])->map(self::CLAIMS);

        self::assertSame(['manager'], $roles);
    }

    public function test_role_par_defaut_si_aucun_role(): void
    {
        self::assertSame(['guest'], $this->resolver(['default' => 'guest'])->map([]));
        self::assertSame([], $this->resolver()->map([]));
    }

    public function test_callback_personnalise(): void
    {
        KeycloakSanctum::mapRolesUsing(fn (array $roles, array $claims) => array_map('strtoupper', $roles));

        self::assertSame(['ADMIN', 'READER'], $this->resolver()->map(self::CLAIMS));
    }

    public function test_source_invalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver(['source' => 'nope'])->map(self::CLAIMS);
    }
}

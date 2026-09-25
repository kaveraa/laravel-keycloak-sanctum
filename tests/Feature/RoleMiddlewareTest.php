<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Kaveraa\KeycloakSanctum\KeycloakSanctum;
use Kaveraa\KeycloakSanctum\Tests\TestCase;

final class RoleMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware(['api', 'auth:sanctum', 'keycloak.role:admin,editor'])
            ->get('/api/admin', fn () => response()->json(['ok' => true, 'is_admin' => KeycloakSanctum::hasAnyRole('admin')]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['keycloak-sanctum.users.auto_create' => true]);
    }

    public function test_role_autorise(): void
    {
        $token = $this->loginWithKeycloak()->json('token');

        $this->withToken($token)->getJson('/api/admin')->assertOk()->assertJson(['ok' => true, 'is_admin' => true]);
    }

    public function test_role_manquant(): void
    {
        $token = $this->loginWithKeycloak(['resource_access' => ['demo-app' => ['roles' => ['reader']]]])->json('token');

        $this->withToken($token)->getJson('/api/admin')->assertForbidden();
    }

    public function test_non_connecte(): void
    {
        $this->getJson('/api/admin')->assertUnauthorized();
    }
}

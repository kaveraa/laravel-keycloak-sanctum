<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Kaveraa\KeycloakSanctum\Contracts\SyncsKeycloakRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements SyncsKeycloakRoles
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['roles' => 'array'];
    }

    public function syncKeycloakRoles(array $roles): void
    {
        $this->forceFill(['roles' => $roles])->save();
    }
}

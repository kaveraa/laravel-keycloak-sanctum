<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Kaveraa\KeycloakSanctum\SocialiteDriver;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * GET /sso/login : redirige vers la page de connexion Keycloak.
 */
class LoginController
{
    public function __invoke(SocialiteDriver $driver): RedirectResponse
    {
        return $driver->make()->redirect();
    }
}

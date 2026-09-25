<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * GET /sso/settings : informations utiles au front (adresse de connexion, délai d'inactivité).
 */
class SettingsController
{
    public function __invoke(): JsonResponse
    {
        $idle = config('keycloak-sanctum.token.idle_timeout');

        return response()->json([
            'login_url' => route('keycloak-sanctum.login'),
            'idle_timeout' => $idle !== null && $idle !== '' ? (int) $idle : null,
        ]);
    }
}

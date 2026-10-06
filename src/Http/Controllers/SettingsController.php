<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * GET /sso/settings: useful information for the frontend (login address, idle timeout).
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

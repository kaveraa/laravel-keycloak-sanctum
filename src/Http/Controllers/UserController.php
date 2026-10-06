<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kaveraa\KeycloakSanctum\KeycloakSanctum;

/**
 * GET /sso/user: logged-in user and the roles (Sanctum token required).
 */
class UserController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'user' => KeycloakSanctum::userPayload($request->user(), KeycloakSanctum::roles($request)),
        ]);
    }
}

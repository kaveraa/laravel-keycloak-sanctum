<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;
use Kaveraa\KeycloakSanctum\KeycloakClient;
use Kaveraa\KeycloakSanctum\Models\KeycloakSession;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * POST /sso/logout: deletes the current Sanctum token and returns the Keycloak logout
 * address, where the frontend redirects to also close the SSO session.
 */
class LogoutController
{
    public function __invoke(Request $request, KeycloakClient $keycloak): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        $idToken = null;

        if ($token instanceof PersonalAccessToken) {
            $session = KeycloakSession::query()->where('personal_access_token_id', $token->getKey())->first();
            $idToken = $session?->id_token;
            $session?->delete();
            $token->delete();
        }

        $postLogout = (string) config('keycloak-sanctum.frontend.post_logout_redirect_uri', '/');
        $postLogout = preg_match('#^https?://#', $postLogout) ? $postLogout : url($postLogout);

        try {
            $logoutUrl = $keycloak->logoutUrl($idToken, $postLogout);
        } catch (KeycloakException) {
            $logoutUrl = null;
        }

        return response()->json(['logout_url' => $logoutUrl]);
    }
}

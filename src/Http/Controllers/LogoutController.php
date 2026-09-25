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
 * POST /sso/logout : supprime le jeton Sanctum actuel et retourne l'adresse de déconnexion
 * Keycloak, vers laquelle le front redirige pour fermer aussi la session SSO.
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

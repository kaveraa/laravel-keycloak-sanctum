<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kaveraa\KeycloakSanctum\Events\KeycloakLogout;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;
use Kaveraa\KeycloakSanctum\KeycloakClient;
use Kaveraa\KeycloakSanctum\Models\KeycloakSession;

/**
 * POST /sso/backchannel-logout : appelé par Keycloak quand une session se termine
 * (déconnexion depuis une autre application, fin de session, action d'un administrateur).
 *
 * Vérifie le "logout token" selon la norme OpenID Connect Back-Channel Logout 1.0,
 * puis supprime les jetons Sanctum de la session.
 */
class RemoteLogoutController
{
    private const EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    public function __invoke(Request $request, KeycloakClient $keycloak): Response|JsonResponse
    {
        $jwt = $request->input('logout_token');
        if (! is_string($jwt) || $jwt === '') {
            return $this->error('logout_token manquant.');
        }

        try {
            $claims = $keycloak->verify($jwt);
        } catch (KeycloakException $e) {
            Log::warning('keycloak-sanctum: logout token refusé', ['exception' => $e->getMessage()]);

            return $this->error('logout_token invalide.');
        }

        $audiences = (array) ($claims['aud'] ?? []);
        $sid = isset($claims['sid']) ? (string) $claims['sid'] : null;
        $sub = isset($claims['sub']) ? (string) $claims['sub'] : null;

        $invalid = match (true) {
            ! in_array($keycloak->clientId(), $audiences, true) => 'destinataire (aud) inattendu',
            ! isset($claims['iat']) => 'date (iat) manquante',
            ! is_array($claims['events'] ?? null) || ! array_key_exists(self::EVENT, $claims['events']) => 'événement de déconnexion manquant',
            array_key_exists('nonce', $claims) => 'nonce interdit',
            $sid === null && $sub === null => 'sid et sub manquants',
            default => null,
        };

        if ($invalid !== null) {
            return $this->error("logout_token invalide : {$invalid}.");
        }

        // Un même logout token ne doit pas être rejoué
        if (isset($claims['jti']) && ! Cache::add('keycloak-sanctum:jti:'.hash('sha256', (string) $claims['jti']), true, 600)) {
            return $this->error('logout_token déjà utilisé.');
        }

        $revoked = KeycloakSession::revoke($sid, $sub);
        event(new KeycloakLogout($sid, $sub, $revoked));

        return response('', 200)->header('Cache-Control', 'no-store');
    }

    private function error(string $description): JsonResponse
    {
        return response()
            ->json(['error' => 'invalid_request', 'error_description' => $description], 400)
            ->header('Cache-Control', 'no-store');
    }
}

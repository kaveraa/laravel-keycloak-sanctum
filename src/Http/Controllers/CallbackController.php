<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kaveraa\KeycloakSanctum\Contracts\SyncsKeycloakRoles;
use Kaveraa\KeycloakSanctum\Events\KeycloakLogin;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;
use Kaveraa\KeycloakSanctum\KeycloakClient;
use Kaveraa\KeycloakSanctum\LoginCodes;
use Kaveraa\KeycloakSanctum\RoleResolver;
use Kaveraa\KeycloakSanctum\SocialiteDriver;
use Kaveraa\KeycloakSanctum\UserResolver;

/**
 * GET /sso/callback : retour de Keycloak après la connexion.
 *
 * Vérifie le jeton, retrouve l'utilisateur, calcule ses rôles, puis redirige vers le front
 * avec un code à usage unique (?code=...), ou avec une erreur (?error=...).
 */
class CallbackController
{
    public function __invoke(
        Request $request,
        SocialiteDriver $driver,
        KeycloakClient $keycloak,
        UserResolver $users,
        RoleResolver $roles,
        LoginCodes $codes,
    ): RedirectResponse {
        // L'utilisateur a annulé, ou Keycloak a refusé la connexion
        if ($request->query('error') !== null) {
            return $this->toFrontend(['error' => 'access_denied']);
        }

        try {
            $keycloakUser = $driver->make()->user();
        } catch (\Throwable $e) {
            Log::warning('keycloak-sanctum: échec de la connexion Keycloak', ['exception' => $e->getMessage()]);

            return $this->toFrontend(['error' => 'authentication_failed']);
        }

        try {
            $tokenClaims = $keycloak->verify((string) $keycloakUser->token);
        } catch (KeycloakException $e) {
            Log::warning('keycloak-sanctum: jeton Keycloak refusé', ['exception' => $e->getMessage()]);

            return $this->toFrontend(['error' => 'invalid_token']);
        }

        // Le jeton doit avoir été délivré pour ce client
        if (($tokenClaims['azp'] ?? null) !== $keycloak->clientId()) {
            return $this->toFrontend(['error' => 'invalid_token']);
        }

        // Informations du profil (userinfo) complétées par le jeton, qui fait foi
        $claims = array_merge((array) $keycloakUser->getRaw(), $tokenClaims);

        $user = $users->resolve($claims);
        if ($user === null) {
            return $this->toFrontend(['error' => 'user_not_found']);
        }

        $appRoles = $roles->map($tokenClaims);
        if ($appRoles === [] && config('keycloak-sanctum.roles.required')) {
            return $this->toFrontend(['error' => 'no_role']);
        }

        if ($user instanceof SyncsKeycloakRoles && config('keycloak-sanctum.roles.sync')) {
            $user->syncKeycloakRoles($appRoles);
        }

        event(new KeycloakLogin($user, $appRoles, $claims));

        $code = $codes->create([
            'user_id' => $user->getKey(),
            'sid' => $tokenClaims['sid'] ?? $tokenClaims['session_state'] ?? null,
            'sub' => (string) $tokenClaims['sub'],
            'roles' => $appRoles,
            // Réponse complète du serveur Keycloak, fournie par socialiteproviders/manager
            'id_token' => property_exists($keycloakUser, 'accessTokenResponseBody')
                ? ($keycloakUser->accessTokenResponseBody['id_token'] ?? null)
                : null,
        ]);

        return $this->toFrontend(['code' => $code]);
    }

    /**
     * @param array<string, string> $query
     */
    private function toFrontend(array $query): RedirectResponse
    {
        $url = (string) config('keycloak-sanctum.frontend.callback_url', '/login/callback');
        $url = preg_match('#^https?://#', $url) ? $url : url($url);

        return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').http_build_query($query));
    }
}

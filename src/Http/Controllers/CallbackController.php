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
 * GET /sso/callback: return from Keycloak after the login.
 *
 * Checks the token, finds the user, computes the roles, then redirects to the frontend
 * with a one-time code (?code=...), or with an error (?error=...).
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
        // The user cancelled, or Keycloak refused the login
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

        // The token must have been issued for this client
        if (($tokenClaims['azp'] ?? null) !== $keycloak->clientId()) {
            return $this->toFrontend(['error' => 'invalid_token']);
        }

        // Profile information (userinfo) completed by the token, which takes precedence
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
            // Full response of the Keycloak server, provided by socialiteproviders/manager
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

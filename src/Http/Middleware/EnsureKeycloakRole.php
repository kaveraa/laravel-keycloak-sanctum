<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kaveraa\KeycloakSanctum\KeycloakSanctum;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorise la route seulement si l'utilisateur a au moins un des rôles donnés.
 *
 *     Route::middleware(['auth:sanctum', 'keycloak.role:admin,editor'])->group(...);
 */
class EnsureKeycloakRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($request->user() === null) {
            abort(401);
        }

        if (array_intersect($roles, KeycloakSanctum::roles($request)) === []) {
            abort(403);
        }

        return $next($request);
    }
}

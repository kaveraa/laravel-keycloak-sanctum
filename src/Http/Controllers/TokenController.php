<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;
use Kaveraa\KeycloakSanctum\KeycloakSanctum;
use Kaveraa\KeycloakSanctum\LoginCodes;
use Kaveraa\KeycloakSanctum\Models\KeycloakSession;
use Kaveraa\KeycloakSanctum\UserResolver;

/**
 * POST /sso/token : échange le code à usage unique contre un jeton Sanctum.
 */
class TokenController
{
    public function __invoke(Request $request, LoginCodes $codes, UserResolver $users): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:255']]);

        $payload = $codes->consume($validated['code']);
        $user = $payload !== null ? $users->find($payload['user_id']) : null;

        if ($payload === null || $user === null) {
            return response()->json([
                'message' => 'Code invalide ou expiré.',
                'error' => 'invalid_code',
            ], 422);
        }

        if (! method_exists($user, 'createToken')) {
            throw new KeycloakException($user::class.' doit utiliser le trait Laravel\Sanctum\HasApiTokens.');
        }

        $expiration = config('keycloak-sanctum.token.expiration');
        $expiresAt = $expiration !== null && $expiration !== '' ? now()->addMinutes((int) $expiration) : null;

        $newToken = $user->createToken((string) config('keycloak-sanctum.token.name', 'keycloak'), ['*'], $expiresAt);

        KeycloakSession::query()->create([
            'personal_access_token_id' => $newToken->accessToken->getKey(),
            'sid' => $payload['sid'],
            'sub' => $payload['sub'],
            'roles' => $payload['roles'],
            'id_token' => $payload['id_token'],
        ]);

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => KeycloakSanctum::userPayload($user, $payload['roles']),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Kaveraa\KeycloakSanctum\Models\KeycloakSession;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Points d'extension du paquet, à appeler dans le boot() d'un ServiceProvider de l'application.
 *
 *     KeycloakSanctum::resolveUsersUsing(fn (array $claims) => User::firstWhere('email', $claims['email']));
 *     KeycloakSanctum::mapRolesUsing(fn (array $roles, array $claims) => ...);
 *     KeycloakSanctum::userPayloadUsing(fn ($user, array $roles) => [...]);
 */
final class KeycloakSanctum
{
    /** @var (callable(array<string, mixed>): ?Model)|null */
    public static $resolveUserCallback = null;

    /** @var (callable(list<string>, array<string, mixed>): array<int, string>)|null */
    public static $mapRolesCallback = null;

    /** @var (callable(Authenticatable, list<string>): array<string, mixed>)|null */
    public static $userPayloadCallback = null;

    /**
     * Remplace la recherche/création de l'utilisateur local.
     * Le callback reçoit les informations Keycloak et retourne un modèle, ou null pour refuser la connexion.
     *
     * @param callable(array<string, mixed>): ?Model $callback
     */
    public static function resolveUsersUsing(callable $callback): void
    {
        self::$resolveUserCallback = $callback;
    }

    /**
     * Remplace la transformation des rôles Keycloak en rôles de l'application.
     *
     * @param callable(list<string>, array<string, mixed>): array<int, string> $callback
     */
    public static function mapRolesUsing(callable $callback): void
    {
        self::$mapRolesCallback = $callback;
    }

    /**
     * Remplace les données de l'utilisateur renvoyées au front (routes token et user).
     *
     * @param callable(Authenticatable, list<string>): array<string, mixed> $callback
     */
    public static function userPayloadUsing(callable $callback): void
    {
        self::$userPayloadCallback = $callback;
    }

    /**
     * Rôles de l'utilisateur connecté, lus sur son jeton Sanctum actuel.
     *
     * @return list<string>
     */
    public static function roles(Request|Authenticatable|null $subject = null): array
    {
        $user = match (true) {
            $subject instanceof Request => $subject->user(),
            $subject instanceof Authenticatable => $subject,
            default => request()->user(),
        };

        $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if (! $token instanceof PersonalAccessToken) {
            return [];
        }

        $session = KeycloakSession::query()->where('personal_access_token_id', $token->getKey())->first();

        return $session?->roles ?? [];
    }

    /**
     * L'utilisateur connecté a-t-il au moins un des rôles donnés ?
     */
    public static function hasAnyRole(string ...$roles): bool
    {
        return array_intersect($roles, self::roles()) !== [];
    }

    /**
     * Données de l'utilisateur envoyées au front.
     *
     * @param list<string> $roles
     *
     * @return array<string, mixed>
     */
    public static function userPayload(Authenticatable $user, array $roles): array
    {
        if (self::$userPayloadCallback !== null) {
            return (self::$userPayloadCallback)($user, $roles);
        }

        $data = $user instanceof Model ? $user->toArray() : ['id' => $user->getAuthIdentifier()];

        return $data + ['roles' => $roles];
    }

    /**
     * Revient au comportement par défaut (utile dans les tests).
     */
    public static function reset(): void
    {
        self::$resolveUserCallback = null;
        self::$mapRolesCallback = null;
        self::$userPayloadCallback = null;
    }
}

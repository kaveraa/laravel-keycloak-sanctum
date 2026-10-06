<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Kaveraa\KeycloakSanctum\Models\KeycloakSession;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Extension points of the package, to call in the boot() of a ServiceProvider of the application.
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
     * Replaces the lookup/creation of the local user.
     * The callback receives the Keycloak information and returns a model, or null to refuse the login.
     *
     * @param callable(array<string, mixed>): ?Model $callback
     */
    public static function resolveUsersUsing(callable $callback): void
    {
        self::$resolveUserCallback = $callback;
    }

    /**
     * Replaces the mapping of Keycloak roles to application roles.
     *
     * @param callable(list<string>, array<string, mixed>): array<int, string> $callback
     */
    public static function mapRolesUsing(callable $callback): void
    {
        self::$mapRolesCallback = $callback;
    }

    /**
     * Replaces the user data returned to the frontend (token and user routes).
     *
     * @param callable(Authenticatable, list<string>): array<string, mixed> $callback
     */
    public static function userPayloadUsing(callable $callback): void
    {
        self::$userPayloadCallback = $callback;
    }

    /**
     * Roles of the logged-in user, read from the current Sanctum token.
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
     * Does the logged-in user have at least one of the given roles?
     */
    public static function hasAnyRole(string ...$roles): bool
    {
        return array_intersect($roles, self::roles()) !== [];
    }

    /**
     * User data sent to the frontend.
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
     * Goes back to the default behavior (useful in tests).
     */
    public static function reset(): void
    {
        self::$resolveUserCallback = null;
        self::$mapRolesCallback = null;
        self::$userPayloadCallback = null;
    }
}

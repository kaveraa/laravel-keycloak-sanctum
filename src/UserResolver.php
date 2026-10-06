<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;

/**
 * Finds (and creates if needed) the local user that matches a Keycloak user.
 */
class UserResolver
{
    /**
     * @param array<string, mixed> $config keycloak-sanctum.users configuration
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param array<string, mixed> $claims Keycloak information (userinfo + access token)
     *
     * @return Model|null null if the user does not exist and automatic creation is disabled
     */
    public function resolve(array $claims): ?Model
    {
        if (KeycloakSanctum::$resolveUserCallback !== null) {
            return (KeycloakSanctum::$resolveUserCallback)($claims);
        }

        $column = (string) ($this->config['identifier']['column'] ?? 'keycloak_id');
        $claim = (string) ($this->config['identifier']['claim'] ?? 'sub');
        $identifier = $claims[$claim] ?? null;

        if (! is_scalar($identifier) || (string) $identifier === '') {
            throw new KeycloakException("Le jeton Keycloak ne contient pas le claim \"{$claim}\" utilisé pour identifier l'utilisateur.");
        }

        $attributes = $this->attributes($claims);
        $model = $this->model();
        $user = $model->newQuery()->where($column, $identifier)->first();

        if ($user === null) {
            if (! ($this->config['auto_create'] ?? false)) {
                return null;
            }

            $user = $model->newInstance();
            $user->forceFill([$column => $identifier]);
        }

        $user->forceFill($attributes);
        if (! $user->exists || $user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    /**
     * Finds a user by primary key (used during the code exchange).
     */
    public function find(mixed $id): ?Model
    {
        return $this->model()->newQuery()->find($id);
    }

    /**
     * @param array<string, mixed> $claims
     *
     * @return array<string, mixed>
     */
    private function attributes(array $claims): array
    {
        $attributes = [];
        foreach ((array) ($this->config['attributes'] ?? []) as $column => $claim) {
            if (array_key_exists($claim, $claims)) {
                $attributes[$column] = $claims[$claim];
            }
        }

        return $attributes;
    }

    private function model(): Model
    {
        $class = (string) ($this->config['model'] ?? 'App\\Models\\User');

        if (! is_a($class, Model::class, true)) {
            throw new KeycloakException("keycloak-sanctum.users.model doit être un modèle Eloquent : {$class}");
        }

        return new $class();
    }
}

<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\KeycloakSanctum\Exceptions\KeycloakException;

/**
 * Retrouve (et si besoin crée) l'utilisateur local correspondant à un utilisateur Keycloak.
 */
class UserResolver
{
    /**
     * @param array<string, mixed> $config configuration keycloak-sanctum.users
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param array<string, mixed> $claims informations Keycloak (userinfo + jeton d'accès)
     *
     * @return Model|null null si l'utilisateur n'existe pas et que la création automatique est désactivée
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
     * Retrouve un utilisateur par sa clé primaire (utilisé lors de l'échange du code).
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

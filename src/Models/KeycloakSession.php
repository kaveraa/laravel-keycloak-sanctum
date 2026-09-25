<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Lien entre un jeton Sanctum et la session Keycloak qui l'a créé.
 * Sert à la déconnexion depuis Keycloak (sid / sub) et à la lecture des rôles.
 *
 * @property int $id
 * @property int $personal_access_token_id
 * @property string|null $sid identifiant de session Keycloak
 * @property string $sub identifiant de l'utilisateur dans Keycloak
 * @property list<string> $roles rôles de l'application
 * @property string|null $id_token jeton d'identité, chiffré en base
 */
class KeycloakSession extends Model
{
    protected $table = 'keycloak_sessions';

    protected $guarded = [];

    protected $hidden = ['id_token'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'id_token' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    /**
     * Supprime les jetons Sanctum (et leurs sessions) correspondant à une session ou un utilisateur Keycloak.
     *
     * @return int nombre de jetons supprimés
     */
    public static function revoke(?string $sid, ?string $sub): int
    {
        $query = static::query();

        if ($sid !== null && $sid !== '') {
            $query->where('sid', $sid);
        } elseif ($sub !== null && $sub !== '') {
            $query->where('sub', $sub);
        } else {
            return 0;
        }

        $tokenIds = $query->pluck('personal_access_token_id')->all();
        if ($tokenIds === []) {
            return 0;
        }

        static::query()->whereIn('personal_access_token_id', $tokenIds)->delete();

        return PersonalAccessToken::query()->whereKey($tokenIds)->delete();
    }
}

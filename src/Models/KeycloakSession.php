<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Link between a Sanctum token and the Keycloak session that created it.
 * Used for the logout from Keycloak (sid / sub) and to read the roles.
 *
 * @property int $id
 * @property int $personal_access_token_id
 * @property string|null $sid Keycloak session id
 * @property string $sub id of the user in Keycloak
 * @property list<string> $roles application roles
 * @property string|null $id_token identity token, encrypted in the database
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
     * Deletes the Sanctum tokens (and their sessions) that match a Keycloak session or user.
     *
     * @return int number of deleted tokens
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

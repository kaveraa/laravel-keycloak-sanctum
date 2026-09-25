<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum\Console;

use Illuminate\Console\Command;

/**
 * php artisan keycloak-sanctum:install
 */
class InstallCommand extends Command
{
    protected $signature = 'keycloak-sanctum:install';

    protected $description = 'Publie la configuration et les migrations de keycloak-sanctum';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'keycloak-sanctum-config']);
        $this->call('vendor:publish', ['--tag' => 'keycloak-sanctum-migrations']);

        if (! glob(database_path('migrations/*_create_personal_access_tokens_table.php'))) {
            $this->warn('La table personal_access_tokens de Sanctum est absente : lancez d\'abord "php artisan install:api".');
        }

        $this->newLine();
        $this->info('Étapes suivantes :');
        $this->line('  1. Ajoutez dans .env : KEYCLOAK_BASE_URL, KEYCLOAK_REALM, KEYCLOAK_CLIENT_ID, KEYCLOAK_CLIENT_SECRET');
        $this->line('  2. Lancez : php artisan migrate');
        $this->line('  3. Ajoutez le trait Laravel\Sanctum\HasApiTokens au modèle User');
        $this->line('  4. Dans Keycloak, déclarez l\'adresse de retour : '.url(config('keycloak-sanctum.routes.prefix', 'sso').'/callback'));
        $this->line('     et l\'adresse de back-channel logout : '.url(config('keycloak-sanctum.routes.prefix', 'sso').'/backchannel-logout'));

        return self::SUCCESS;
    }
}

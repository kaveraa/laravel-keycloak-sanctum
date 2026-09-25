<?php

declare(strict_types=1);

namespace Kaveraa\KeycloakSanctum;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Kaveraa\KeycloakSanctum\Console\InstallCommand;
use Kaveraa\KeycloakSanctum\Http\Middleware\EnsureKeycloakRole;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class KeycloakSanctumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/keycloak-sanctum.php', 'keycloak-sanctum');

        $this->app->singleton(KeycloakClient::class, fn (Application $app) => new KeycloakClient(
            $app['config']->get('keycloak-sanctum'),
            $app['cache']->store($app['config']->get('keycloak-sanctum.cache.store')),
        ));

        $this->app->bind(RoleResolver::class, fn (Application $app) => new RoleResolver(
            (array) $app['config']->get('keycloak-sanctum.roles'),
            (string) $app['config']->get('keycloak-sanctum.client_id'),
        ));

        $this->app->bind(UserResolver::class, fn (Application $app) => new UserResolver(
            (array) $app['config']->get('keycloak-sanctum.users'),
        ));

        $this->app->bind(LoginCodes::class, fn (Application $app) => new LoginCodes(
            $app['cache']->store($app['config']->get('keycloak-sanctum.cache.store')),
            (int) $app['config']->get('keycloak-sanctum.exchange_code_ttl', 60),
        ));

        $this->app->bind(SocialiteDriver::class, fn (Application $app) => new SocialiteDriver(
            (array) $app['config']->get('keycloak-sanctum'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/keycloak-sanctum.php' => config_path('keycloak-sanctum.php'),
            ], 'keycloak-sanctum-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'keycloak-sanctum-migrations');

            $this->commands([InstallCommand::class]);
        }

        if (config('keycloak-sanctum.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/keycloak-sanctum.php');
        }

        $this->app->make(Router::class)->aliasMiddleware('keycloak.role', EnsureKeycloakRole::class);

        // Ajoute le pilote "keycloak" à Socialite
        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);

        $this->registerIdleTimeout();
    }

    /**
     * Refuse les jetons du paquet restés inutilisés plus longtemps que token.idle_timeout.
     */
    private function registerIdleTimeout(): void
    {
        $idle = config('keycloak-sanctum.token.idle_timeout');
        if ($idle === null || $idle === '' || (int) $idle <= 0) {
            return;
        }

        $tokenName = (string) config('keycloak-sanctum.token.name', 'keycloak');

        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $isValid) use ($idle, $tokenName): bool {
            if (! $isValid || $token->name !== $tokenName) {
                return $isValid;
            }

            $lastActivity = $token->last_used_at ?? $token->created_at;

            return $lastActivity === null || $lastActivity->gt(now()->subMinutes((int) $idle));
        });
    }
}

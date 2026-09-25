<?php

use Illuminate\Support\Facades\Route;
use Kaveraa\KeycloakSanctum\Http\Controllers\CallbackController;
use Kaveraa\KeycloakSanctum\Http\Controllers\LoginController;
use Kaveraa\KeycloakSanctum\Http\Controllers\LogoutController;
use Kaveraa\KeycloakSanctum\Http\Controllers\RemoteLogoutController;
use Kaveraa\KeycloakSanctum\Http\Controllers\SettingsController;
use Kaveraa\KeycloakSanctum\Http\Controllers\TokenController;
use Kaveraa\KeycloakSanctum\Http\Controllers\UserController;

$config = config('keycloak-sanctum.routes');

Route::prefix($config['prefix'] ?? 'sso')->name('keycloak-sanctum.')->group(function () use ($config) {
    // Connexion : la session est nécessaire pour le paramètre "state" de OAuth
    Route::middleware($config['web_middleware'] ?? ['web'])->group(function () {
        Route::get('login', LoginController::class)->name('login');
        Route::get('callback', CallbackController::class)->name('callback');
    });

    // Routes appelées par le front
    Route::middleware($config['api_middleware'] ?? ['api'])->group(function () {
        Route::get('settings', SettingsController::class)->name('settings');
        Route::post('token', TokenController::class)->middleware('throttle:20,1')->name('token');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('user', UserController::class)->name('user');
            Route::post('logout', LogoutController::class)->name('logout');
        });
    });

    // Appelé par le serveur Keycloak : pas de session, pas de protection CSRF
    Route::post('backchannel-logout', RemoteLogoutController::class)->name('backchannel-logout');
});

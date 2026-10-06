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
    // Login: the session is needed for the OAuth "state" parameter
    Route::middleware($config['web_middleware'] ?? ['web'])->group(function () {
        Route::get('login', LoginController::class)->name('login');
        Route::get('callback', CallbackController::class)->name('callback');
    });

    // Routes called by the frontend
    Route::middleware($config['api_middleware'] ?? ['api'])->group(function () {
        Route::get('settings', SettingsController::class)->name('settings');
        Route::post('token', TokenController::class)->middleware('throttle:20,1')->name('token');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('user', UserController::class)->name('user');
            Route::post('logout', LogoutController::class)->name('logout');
        });
    });

    // Called by the Keycloak server: no session, no CSRF protection
    Route::post('backchannel-logout', RemoteLogoutController::class)->name('backchannel-logout');
});

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Serveur Keycloak
    |--------------------------------------------------------------------------
    |
    | base_url : adresse du serveur, sans /realms (ex : https://sso.example.org).
    | Pour Keycloak < 17, ajoutez /auth (ex : https://sso.example.org/auth).
    |
    */

    'base_url' => env('KEYCLOAK_BASE_URL'),

    'realm' => env('KEYCLOAK_REALM'),

    'client_id' => env('KEYCLOAK_CLIENT_ID'),

    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),

    // Adresse de retour après la connexion Keycloak.
    // null = la route du paquet : {APP_URL}/sso/callback
    'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),

    'scopes' => ['openid', 'profile', 'email'],

    /*
    |--------------------------------------------------------------------------
    | Application front (SPA)
    |--------------------------------------------------------------------------
    |
    | callback_url : page du front qui reçoit ?code=... après la connexion et
    | l'échange contre un jeton Sanctum (ou ?error=... en cas d'échec).
    |
    | post_logout_redirect_uri : page affichée après la déconnexion Keycloak.
    | Elle doit être autorisée dans le client Keycloak ("Valid post logout redirect URIs").
    |
    */

    'frontend' => [
        'callback_url' => env('KEYCLOAK_SANCTUM_FRONTEND_CALLBACK_URL', '/login/callback'),
        'post_logout_redirect_uri' => env('KEYCLOAK_SANCTUM_POST_LOGOUT_REDIRECT_URI', '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Utilisateurs
    |--------------------------------------------------------------------------
    |
    | identifier : colonne de la table users qui relie l'utilisateur à Keycloak,
    | et claim Keycloak correspondant. Exemples :
    |   ['column' => 'keycloak_id', 'claim' => 'sub']  (identifiant Keycloak, recommandé)
    |   ['column' => 'email', 'claim' => 'email']
    |   ['column' => 'username', 'claim' => 'preferred_username']
    |
    | auto_create : créer l'utilisateur à sa première connexion. Si false,
    | seuls les utilisateurs déjà présents en base peuvent se connecter.
    |
    | attributes : colonnes mises à jour à chaque connexion => claim Keycloak.
    |
    */

    'users' => [
        'model' => 'App\\Models\\User',
        'identifier' => [
            'column' => 'keycloak_id',
            'claim' => 'sub',
        ],
        'auto_create' => (bool) env('KEYCLOAK_SANCTUM_AUTO_CREATE_USERS', false),
        'attributes' => [
            'name' => 'name',
            'email' => 'email',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rôles
    |--------------------------------------------------------------------------
    |
    | source : où lire les rôles dans le jeton Keycloak.
    |   'realm'  = rôles du realm (realm_access.roles)
    |   'client' = rôles du client (resource_access.{client}.roles)
    |   'both'   = les deux
    |
    | map : rôle Keycloak => rôle de l'application. Si la liste est vide, les
    | rôles Keycloak sont gardés tels quels. Sinon, seuls les rôles de la liste
    | sont gardés.
    |
    | default : rôle donné quand l'utilisateur n'a aucun rôle (null = aucun).
    |
    | required : refuser la connexion d'un utilisateur sans rôle.
    |
    | sync : appeler syncKeycloakRoles() sur le modèle User s'il implémente
    | Kaveraa\KeycloakSanctum\Contracts\SyncsKeycloakRoles.
    |
    */

    'roles' => [
        'source' => 'client',
        'client' => null, // null = client_id
        'map' => [],
        'default' => env('KEYCLOAK_SANCTUM_DEFAULT_ROLE'),
        'required' => false,
        'sync' => (bool) env('KEYCLOAK_SANCTUM_SYNC_ROLES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jeton Sanctum
    |--------------------------------------------------------------------------
    |
    | expiration : durée de vie maximale du jeton, en minutes (null = illimitée).
    |
    | idle_timeout : déconnexion après ce nombre de minutes sans requête
    | (null = désactivé). Seuls les jetons créés par ce paquet sont concernés.
    |
    */

    'token' => [
        'name' => 'keycloak',
        'expiration' => env('KEYCLOAK_SANCTUM_TOKEN_EXPIRATION'),
        'idle_timeout' => env('KEYCLOAK_SANCTUM_IDLE_TIMEOUT'),
    ],

    // Durée de validité du code à usage unique échangé contre le jeton, en secondes
    'exchange_code_ttl' => 60,

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'sso',
        // Routes de connexion : la session est nécessaire pour vérifier le paramètre "state"
        'web_middleware' => ['web'],
        // Routes appelées par le front en JSON
        'api_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache et HTTP
    |--------------------------------------------------------------------------
    |
    | La configuration OpenID et les clés publiques de Keycloak sont gardées en
    | cache. Les clés sont rechargées automatiquement quand Keycloak en change.
    |
    */

    'cache' => [
        'store' => null,
        'ttl' => 3600,
    ],

    'http' => [
        'timeout' => 5,
        'verify' => true,
    ],

    // Tolérance sur l'heure des jetons, en secondes (horloges pas tout à fait synchronisées)
    'leeway' => 30,

];

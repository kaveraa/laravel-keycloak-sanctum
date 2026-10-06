<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Keycloak server
    |--------------------------------------------------------------------------
    |
    | base_url: address of the server, without /realms (example: https://sso.example.org).
    | For Keycloak < 17, add /auth (example: https://sso.example.org/auth).
    |
    */

    'base_url' => env('KEYCLOAK_BASE_URL'),

    'realm' => env('KEYCLOAK_REALM'),

    'client_id' => env('KEYCLOAK_CLIENT_ID'),

    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),

    // Return address after the Keycloak login.
    // null = the route of the package: {APP_URL}/sso/callback
    'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),

    'scopes' => ['openid', 'profile', 'email'],

    /*
    |--------------------------------------------------------------------------
    | Frontend application (SPA)
    |--------------------------------------------------------------------------
    |
    | callback_url: frontend page that receives ?code=... after the login, to
    | exchange it for a Sanctum token (or ?error=... when the login fails).
    |
    | post_logout_redirect_uri: page shown after the Keycloak logout.
    | It must be allowed in the Keycloak client ("Valid post logout redirect URIs").
    |
    */

    'frontend' => [
        'callback_url' => env('KEYCLOAK_SANCTUM_FRONTEND_CALLBACK_URL', '/login/callback'),
        'post_logout_redirect_uri' => env('KEYCLOAK_SANCTUM_POST_LOGOUT_REDIRECT_URI', '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    |
    | identifier: column of the users table that links the user to Keycloak,
    | and the matching Keycloak claim. Examples:
    |   ['column' => 'keycloak_id', 'claim' => 'sub']  (Keycloak id, recommended)
    |   ['column' => 'email', 'claim' => 'email']
    |   ['column' => 'username', 'claim' => 'preferred_username']
    |
    | auto_create: create the user on the first login. If false,
    | only users already present in the database can log in.
    |
    | attributes: columns updated on every login => Keycloak claim.
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
    | Roles
    |--------------------------------------------------------------------------
    |
    | source: where to read the roles in the Keycloak token.
    |   'realm'  = roles of the realm (realm_access.roles)
    |   'client' = roles of the client (resource_access.{client}.roles)
    |   'both'   = both
    |
    | map: Keycloak role => application role. If the list is empty, the
    | Keycloak roles are kept as they are. Otherwise, only the roles of the list
    | are kept.
    |
    | default: role given when the user has no role (null = none).
    |
    | required: refuse the login of a user without any role.
    |
    | sync: call syncKeycloakRoles() on the User model if it implements
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
    | Sanctum token
    |--------------------------------------------------------------------------
    |
    | expiration: maximum lifetime of the token, in minutes (null = unlimited).
    |
    | idle_timeout: logout after this number of minutes without any request
    | (null = disabled). Only the tokens created by this package are affected.
    |
    */

    'token' => [
        'name' => 'keycloak',
        'expiration' => env('KEYCLOAK_SANCTUM_TOKEN_EXPIRATION'),
        'idle_timeout' => env('KEYCLOAK_SANCTUM_IDLE_TIMEOUT'),
    ],

    // Lifetime of the one-time code exchanged for the token, in seconds
    'exchange_code_ttl' => 60,

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'sso',
        // Login routes: the session is needed to check the "state" parameter
        'web_middleware' => ['web'],
        // Routes called by the frontend in JSON
        'api_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache and HTTP
    |--------------------------------------------------------------------------
    |
    | The OpenID configuration and the public keys of Keycloak are kept in
    | the cache. The keys are reloaded automatically when Keycloak changes them.
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

    // Tolerance on the token times, in seconds (clocks are not always exactly in sync)
    'leeway' => 30,

];

# Laravel Keycloak Sanctum

<p align="center"><img src="https://raw.githubusercontent.com/kaveraa/laravel-keycloak-sanctum/main/art/banner.svg" alt="Laravel Keycloak Sanctum" width="100%"></p>

[![Tests](https://github.com/kaveraa/laravel-keycloak-sanctum/actions/workflows/tests.yml/badge.svg)](https://github.com/kaveraa/laravel-keycloak-sanctum/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/kaveraa/laravel-keycloak-sanctum.svg)](https://packagist.org/packages/kaveraa/laravel-keycloak-sanctum)
[![License](https://img.shields.io/github/license/kaveraa/laravel-keycloak-sanctum.svg)](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/kaveraa/laravel-keycloak-sanctum.svg)](https://packagist.org/packages/kaveraa/laravel-keycloak-sanctum)
[![PHP](https://img.shields.io/packagist/dependency-v/kaveraa/laravel-keycloak-sanctum/php.svg)](https://packagist.org/packages/kaveraa/laravel-keycloak-sanctum)

[Français](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/README.md) - **English**

**Keycloak login (SSO)** for a **front-end application (Vue, React, Angular...) with a Laravel API**.

After the Keycloak login, your front-end gets a normal **Sanctum token** to call your API. The package does the rest:

- **Secure login**: the token is never in a URL. The front-end gets a one-time code (valid for 60 seconds) and exchanges it for the token.
- **Users**: found or created at login, with their Keycloak information (name, email...).
- **Roles**: read from the Keycloak token, changed into the roles of your application, with a `keycloak.role:admin` middleware.
- **Logout from Keycloak** (back-channel logout): when the session ends in Keycloak, the Sanctum tokens are deleted.
- **Logout after inactivity** (optional).
- **Token checks**: signature, dates, issuer, audience. The Keycloak keys are cached and reloaded automatically when Keycloak changes them.

---

## Contents

- [How it works](#how-it-works)
- [Installation](#installation)
- [Configure Keycloak](#configure-keycloak)
- [Front-end](#front-end)
- [Routes](#routes)
- [Users](#users)
- [Roles](#roles)
- [Logout](#logout)
- [Customize](#customize)
- [Events](#events)
- [Security](#security)
- [Development](#development)

## How it works

```
Front-end                     Laravel API                         Keycloak
  |                               |                                  |
  | 1. opens /sso/login --------> | 2. redirects --------------------> | the user logs in
  |                               | <---------- 3. back to /sso/callback |
  |                               | 4. checks the token, finds the user, computes the roles
  | <---- 5. redirects to the front-end with ?code=...               |
  | 6. POST /sso/token {code} --> |                                  |
  | <---- 7. { token, user } ---- |                                  |
  | 8. API calls with "Authorization: Bearer <token>"                |
```

## Installation

Requirements: PHP 8.2+, Laravel 12 or 13, and Sanctum installed (`php artisan install:api`).

```bash
composer require kaveraa/laravel-keycloak-sanctum
```

```bash
php artisan keycloak-sanctum:install
```

```bash
php artisan migrate
```

The `install` command publishes the `config/keycloak-sanctum.php` file and two migrations:
- `keycloak_sessions`: links each Sanctum token to its Keycloak session;
- `keycloak_id` on the `users` table: the Keycloak id of the user. Delete this migration if you find your users by email (see [Users](#users)).

Add the Sanctum trait to the `User` model:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

Then fill in `.env`:

```dotenv
KEYCLOAK_BASE_URL=https://sso.example.org
KEYCLOAK_REALM=my-realm
KEYCLOAK_CLIENT_ID=my-application
KEYCLOAK_CLIENT_SECRET=client-secret

# Front-end page that receives ?code=... (full address if the front-end is on another domain)
KEYCLOAK_SANCTUM_FRONTEND_CALLBACK_URL=https://app.example.org/login/callback
# Page shown after logout
KEYCLOAK_SANCTUM_POST_LOGOUT_REDIRECT_URI=https://app.example.org/
```

All the options, with explanations, are in [config/keycloak-sanctum.php](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/config/keycloak-sanctum.php) (comments in French).

| Variable | Default | Use |
|---|---|---|
| `KEYCLOAK_BASE_URL` | - | Server address, without `/realms` (add `/auth` for Keycloak < 17) |
| `KEYCLOAK_REALM` | - | Realm name |
| `KEYCLOAK_CLIENT_ID` / `KEYCLOAK_CLIENT_SECRET` | - | Keycloak client of the application |
| `KEYCLOAK_REDIRECT_URI` | `{APP_URL}/sso/callback` | Return address after login |
| `KEYCLOAK_SANCTUM_FRONTEND_CALLBACK_URL` | `/login/callback` | Front-end page that receives the code |
| `KEYCLOAK_SANCTUM_POST_LOGOUT_REDIRECT_URI` | `/` | Page shown after logout |
| `KEYCLOAK_SANCTUM_AUTO_CREATE_USERS` | `false` | Create the user at the first login |
| `KEYCLOAK_SANCTUM_DEFAULT_ROLE` | - | Role given to a user with no role |
| `KEYCLOAK_SANCTUM_SYNC_ROLES` | `true` | Save the roles on the user (see [Roles](#roles)) |
| `KEYCLOAK_SANCTUM_TOKEN_EXPIRATION` | - | Maximum token lifetime, in minutes |
| `KEYCLOAK_SANCTUM_IDLE_TIMEOUT` | - | Logout after X minutes with no request |

## Configure Keycloak

In the Keycloak admin console, create (or open) the client of your application:

| Setting | Value |
|---|---|
| Client authentication | **On** (confidential client, with a secret) |
| Standard flow | **On** |
| Valid redirect URIs | `https://api.example.org/sso/callback` |
| Valid post logout redirect URIs | `https://app.example.org/*` |
| Backchannel logout URL | `https://api.example.org/sso/backchannel-logout` |
| Backchannel logout session required | **On** |

The `php artisan keycloak-sanctum:install` command shows the exact addresses of your application.

## Front-end

Example in JavaScript, with no dependency. It works with Vue, React or Angular.

**1. Login button**: open the login route of the API.

```js
window.location.href = 'https://api.example.org/sso/login'
```

**2. `/login/callback` page**: exchange the code for the token.

```js
const params = new URLSearchParams(window.location.search)

if (params.has('error')) {
  // access_denied, authentication_failed, invalid_token, user_not_found, no_role
  showError(params.get('error'))
} else {
  const response = await fetch('https://api.example.org/sso/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ code: params.get('code') }),
  })
  const { token, user } = await response.json()
  localStorage.setItem('token', token)
  // user contains the user information and the roles: user.roles
}
```

**3. API calls**: send the token.

```js
fetch('https://api.example.org/api/projects', {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, Accept: 'application/json' },
})
```

**4. Logout**: delete the token, then close the Keycloak session.

```js
const response = await fetch('https://api.example.org/sso/logout', {
  method: 'POST',
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, Accept: 'application/json' },
})
const { logout_url } = await response.json()
localStorage.removeItem('token')
window.location.href = logout_url ?? '/'
```

If the API answers `401`, the token is not valid any more (logout from Keycloak, inactivity, expiration): send the user back to the login.

## Routes

All routes use the `/sso` prefix (`routes.prefix` option).

| Method | Address | Access | Use |
|---|---|---|---|
| GET | `/sso/login` | public | Redirects to the Keycloak login page |
| GET | `/sso/callback` | Keycloak | Return from Keycloak, redirects to the front-end with `?code=` or `?error=` |
| POST | `/sso/token` | public | Exchanges the code for `{ token, token_type, expires_at, user }` |
| GET | `/sso/user` | token | Logged-in user and roles |
| POST | `/sso/logout` | token | Deletes the token, returns `{ logout_url }` |
| POST | `/sso/backchannel-logout` | Keycloak | End of session sent by Keycloak |
| GET | `/sso/settings` | public | `{ login_url, idle_timeout }` for the front-end |

To declare your own routes, turn off the routes of the package (`routes.enabled => false`) and copy [routes/keycloak-sanctum.php](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/routes/keycloak-sanctum.php).

## Users

At each login, the user is found with a column of the `users` table and a Keycloak value (claim):

```php
// config/keycloak-sanctum.php
'users' => [
    'identifier' => ['column' => 'keycloak_id', 'claim' => 'sub'], // default: Keycloak id
    // 'identifier' => ['column' => 'email', 'claim' => 'email'],  // or by email
    'auto_create' => env('KEYCLOAK_SANCTUM_AUTO_CREATE_USERS', false),
    'attributes' => [          // columns updated at each login => Keycloak claim
        'name' => 'name',
        'email' => 'email',
    ],
],
```

- If the user does not exist and `auto_create` is `false`, the login is refused (`?error=user_not_found`).
- The available claims come from the Keycloak token and profile: `sub`, `email`, `name`, `given_name`, `family_name`, `preferred_username`, and your custom attributes.

## Roles

The roles are read from the Keycloak token, then changed into roles of the application:

```php
'roles' => [
    'source' => 'client',   // 'client' (client roles), 'realm' (realm roles) or 'both'
    'map' => [              // Keycloak role => application role
        'app-admin' => 'admin',
        'app-editor' => 'editor',
    ],
    'default' => null,      // role given to a user with no role
    'required' => false,    // refuse the login with no role (?error=no_role)
],
```

If `map` is empty, the Keycloak roles are kept as they are. If not, only the roles in the list are kept.

**Protect routes:**

```php
Route::middleware(['auth:sanctum', 'keycloak.role:admin,editor'])->group(function () {
    // allowed with the admin OR editor role
});
```

**Read the roles in the code:**

```php
use Kaveraa\KeycloakSanctum\KeycloakSanctum;

KeycloakSanctum::roles();               // ['admin', 'editor']
KeycloakSanctum::hasAnyRole('admin');   // true
```

**Save the roles on the user** (roles table, permissions package...): implement `SyncsKeycloakRoles` on the `User` model. The method is called at each login.

```php
use Kaveraa\KeycloakSanctum\Contracts\SyncsKeycloakRoles;

class User extends Authenticatable implements SyncsKeycloakRoles
{
    public function syncKeycloakRoles(array $roles): void
    {
        $this->syncRoles($roles); // for example with spatie/laravel-permission
    }
}
```

## Logout

- **From the front-end**: `POST /sso/logout` deletes the token and returns the Keycloak logout address (see [Front-end](#front-end)).
- **From Keycloak**: when the session ends in Keycloak (logout from another application, admin action, end of session), Keycloak calls `/sso/backchannel-logout` and the tokens of the session are deleted.
- **After inactivity**: with `KEYCLOAK_SANCTUM_IDLE_TIMEOUT=30`, a token not used for 30 minutes is refused. Each request resets the delay. Only the tokens created by this package are affected.
- **Maximum lifetime**: with `KEYCLOAK_SANCTUM_TOKEN_EXPIRATION=480`, the token expires 8 hours after login, even with activity.

> **Warning:** the inactivity option uses `Sanctum::authenticateAccessTokensUsing()`. If your application already uses this function, leave the option empty and call your own logic.

## Customize

Put this in the `boot()` method of a service provider of the application:

```php
use Kaveraa\KeycloakSanctum\KeycloakSanctum;

// Find the user your way (returning null refuses the login)
KeycloakSanctum::resolveUsersUsing(function (array $claims) {
    return User::firstWhere('email', $claims['email']);
});

// Compute the roles your way
KeycloakSanctum::mapRolesUsing(function (array $keycloakRoles, array $claims) {
    return in_array('super-user', $keycloakRoles) ? ['admin'] : ['reader'];
});

// Choose the user data sent to the front-end (/sso/token and /sso/user routes)
KeycloakSanctum::userPayloadUsing(function ($user, array $roles) {
    return ['id' => $user->id, 'name' => $user->name, 'roles' => $roles];
});
```

By default, the user is sent with `$user->toArray()` (the `$hidden` fields are not sent) and the roles.

## Events

| Event | When | Data |
|---|---|---|
| `Kaveraa\KeycloakSanctum\Events\KeycloakLogin` | After a successful login | `$user`, `$roles`, `$claims` |
| `Kaveraa\KeycloakSanctum\Events\KeycloakLogout` | After an end of session sent by Keycloak | `$sid`, `$sub`, `$revokedTokens` |

## Security

- The Keycloak tokens are checked at each step: signature (public keys of the realm), dates (with 30 seconds of tolerance), issuer and target client.
- The ends of session sent by Keycloak follow the OpenID Connect Back-Channel Logout 1.0 standard: audience, event, no `nonce`, and replay protection.
- The exchange code can be used only once, is valid for 60 seconds, and only its hash is stored.
- The `id_token` (used for the Keycloak logout) is encrypted in the database with the application key.
- The Keycloak keys are reloaded at most once per minute: fake tokens cannot make the package call Keycloak again and again.

To report a security problem, open a [private security advisory](https://github.com/kaveraa/laravel-keycloak-sanctum/security/advisories/new), not a public issue.

## Development

```bash
git clone https://github.com/kaveraa/laravel-keycloak-sanctum.git
cd laravel-keycloak-sanctum
composer install
composer test
```

The tests use a fake Keycloak server: real RSA keys and real signed tokens, with no server to install.

To propose a change, read the [CONTRIBUTING.md](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/CONTRIBUTING.md) guide. See the [CHANGELOG](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/CHANGELOG.md) for the list of versions.

## License

MIT. See [LICENSE](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/LICENSE).

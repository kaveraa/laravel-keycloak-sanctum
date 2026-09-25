# Laravel Keycloak Sanctum

<p align="center"><img src="https://raw.githubusercontent.com/kaveraa/laravel-keycloak-sanctum/main/art/banner.svg" alt="Laravel Keycloak Sanctum" width="100%"></p>

[![Tests](https://github.com/kaveraa/laravel-keycloak-sanctum/actions/workflows/tests.yml/badge.svg)](https://github.com/kaveraa/laravel-keycloak-sanctum/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/kaveraa/laravel-keycloak-sanctum.svg)](https://packagist.org/packages/kaveraa/laravel-keycloak-sanctum)
[![Licence](https://img.shields.io/github/license/kaveraa/laravel-keycloak-sanctum.svg)](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/kaveraa/laravel-keycloak-sanctum.svg)](https://packagist.org/packages/kaveraa/laravel-keycloak-sanctum)
[![PHP](https://img.shields.io/packagist/dependency-v/kaveraa/laravel-keycloak-sanctum/php.svg)](https://packagist.org/packages/kaveraa/laravel-keycloak-sanctum)

**Français** - [English](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/README.en.md)

Connexion **Keycloak (SSO)** pour une **application front (Vue, React, Angular...) avec une API Laravel**.

Après la connexion Keycloak, votre front reçoit un **jeton Sanctum** classique pour appeler votre API. Le paquet s'occupe du reste :

- **Connexion sécurisée** : le jeton n'apparaît jamais dans une URL. Le front reçoit un code à usage unique (valable 60 secondes) et l'échange contre le jeton.
- **Utilisateurs** : retrouvés ou créés à la connexion, avec leurs informations Keycloak (nom, e-mail...).
- **Rôles** : lus dans le jeton Keycloak, traduits en rôles de votre application, avec un middleware `keycloak.role:admin`.
- **Déconnexion depuis Keycloak** (back-channel logout) : quand la session se termine dans Keycloak, les jetons Sanctum sont supprimés.
- **Déconnexion après inactivité** (facultative).
- **Vérification des jetons** : signature, dates, émetteur, destinataire. Les clés de Keycloak sont gardées en cache et rechargées automatiquement quand Keycloak en change.

---

## Sommaire

- [Comment ça marche](#comment-ça-marche)
- [Installation](#installation)
- [Configurer Keycloak](#configurer-keycloak)
- [Côté front](#côté-front)
- [Les routes](#les-routes)
- [Les utilisateurs](#les-utilisateurs)
- [Les rôles](#les-rôles)
- [Déconnexion](#déconnexion)
- [Personnaliser](#personnaliser)
- [Événements](#événements)
- [Sécurité](#sécurité)
- [Développement](#développement)

## Comment ça marche

```
Front                         API Laravel                         Keycloak
  |                               |                                  |
  | 1. ouvre /sso/login --------> | 2. redirige ---------------------> | l'utilisateur se connecte
  |                               | <----------- 3. retour /sso/callback |
  |                               | 4. vérifie le jeton, retrouve l'utilisateur, calcule ses rôles
  | <---- 5. redirige vers le front avec ?code=...                   |
  | 6. POST /sso/token {code} --> |                                  |
  | <---- 7. { token, user } ---- |                                  |
  | 8. appels API avec "Authorization: Bearer <token>"               |
```

## Installation

Prérequis : PHP 8.2+, Laravel 12 ou 13, et Sanctum installé (`php artisan install:api`).

```bash
composer require kaveraa/laravel-keycloak-sanctum
```

```bash
php artisan keycloak-sanctum:install
```

```bash
php artisan migrate
```

La commande `install` publie le fichier `config/keycloak-sanctum.php` et deux migrations :
- `keycloak_sessions` : relie chaque jeton Sanctum à sa session Keycloak ;
- `keycloak_id` sur la table `users` : identifiant Keycloak de l'utilisateur. Supprimez cette migration si vous identifiez vos utilisateurs par e-mail (voir [Les utilisateurs](#les-utilisateurs)).

Ajoutez le trait Sanctum au modèle `User` :

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

Puis renseignez `.env` :

```dotenv
KEYCLOAK_BASE_URL=https://sso.example.org
KEYCLOAK_REALM=mon-realm
KEYCLOAK_CLIENT_ID=mon-application
KEYCLOAK_CLIENT_SECRET=secret-du-client

# Page du front qui reçoit ?code=... (adresse complète si le front est sur un autre domaine)
KEYCLOAK_SANCTUM_FRONTEND_CALLBACK_URL=https://app.example.org/login/callback
# Page affichée après la déconnexion
KEYCLOAK_SANCTUM_POST_LOGOUT_REDIRECT_URI=https://app.example.org/
```

Toutes les options, avec leur explication, sont dans [config/keycloak-sanctum.php](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/config/keycloak-sanctum.php).

| Variable | Défaut | Rôle |
|---|---|---|
| `KEYCLOAK_BASE_URL` | - | Adresse du serveur, sans `/realms` (ajoutez `/auth` pour Keycloak < 17) |
| `KEYCLOAK_REALM` | - | Nom du realm |
| `KEYCLOAK_CLIENT_ID` / `KEYCLOAK_CLIENT_SECRET` | - | Client Keycloak de l'application |
| `KEYCLOAK_REDIRECT_URI` | `{APP_URL}/sso/callback` | Adresse de retour après la connexion |
| `KEYCLOAK_SANCTUM_FRONTEND_CALLBACK_URL` | `/login/callback` | Page du front qui reçoit le code |
| `KEYCLOAK_SANCTUM_POST_LOGOUT_REDIRECT_URI` | `/` | Page affichée après la déconnexion |
| `KEYCLOAK_SANCTUM_AUTO_CREATE_USERS` | `false` | Créer l'utilisateur à sa première connexion |
| `KEYCLOAK_SANCTUM_DEFAULT_ROLE` | - | Rôle donné à un utilisateur sans rôle |
| `KEYCLOAK_SANCTUM_SYNC_ROLES` | `true` | Enregistrer les rôles sur l'utilisateur (voir [Les rôles](#les-rôles)) |
| `KEYCLOAK_SANCTUM_TOKEN_EXPIRATION` | - | Durée de vie maximale du jeton, en minutes |
| `KEYCLOAK_SANCTUM_IDLE_TIMEOUT` | - | Déconnexion après X minutes sans requête |

## Configurer Keycloak

Dans la console d'administration Keycloak, créez (ou ouvrez) le client de votre application :

| Réglage | Valeur |
|---|---|
| Client authentication | **On** (client confidentiel, avec un secret) |
| Standard flow | **On** |
| Valid redirect URIs | `https://api.example.org/sso/callback` |
| Valid post logout redirect URIs | `https://app.example.org/*` |
| Backchannel logout URL | `https://api.example.org/sso/backchannel-logout` |
| Backchannel logout session required | **On** |

La commande `php artisan keycloak-sanctum:install` affiche les adresses exactes de votre application.

## Côté front

Exemple en JavaScript, sans dépendance. Il s'adapte à Vue, React ou Angular.

**1. Bouton de connexion** : ouvrir la route de connexion de l'API.

```js
window.location.href = 'https://api.example.org/sso/login'
```

**2. Page `/login/callback`** : échanger le code contre le jeton.

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
  // user contient les informations de l'utilisateur et ses rôles : user.roles
}
```

**3. Appels à l'API** : envoyer le jeton.

```js
fetch('https://api.example.org/api/projects', {
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, Accept: 'application/json' },
})
```

**4. Déconnexion** : supprimer le jeton, puis fermer la session Keycloak.

```js
const response = await fetch('https://api.example.org/sso/logout', {
  method: 'POST',
  headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, Accept: 'application/json' },
})
const { logout_url } = await response.json()
localStorage.removeItem('token')
window.location.href = logout_url ?? '/'
```

Si l'API répond `401`, le jeton n'est plus valable (déconnexion depuis Keycloak, inactivité, expiration) : renvoyez l'utilisateur vers la connexion.

## Les routes

Toutes les routes sont sous le préfixe `/sso` (option `routes.prefix`).

| Méthode | Adresse | Accès | Rôle |
|---|---|---|---|
| GET | `/sso/login` | public | Redirige vers la page de connexion Keycloak |
| GET | `/sso/callback` | Keycloak | Retour de Keycloak, redirige vers le front avec `?code=` ou `?error=` |
| POST | `/sso/token` | public | Échange le code contre `{ token, token_type, expires_at, user }` |
| GET | `/sso/user` | jeton | Utilisateur connecté et ses rôles |
| POST | `/sso/logout` | jeton | Supprime le jeton, retourne `{ logout_url }` |
| POST | `/sso/backchannel-logout` | Keycloak | Fin de session envoyée par Keycloak |
| GET | `/sso/settings` | public | `{ login_url, idle_timeout }` pour le front |

Pour déclarer vos propres routes, désactivez celles du paquet (`routes.enabled => false`) et copiez [routes/keycloak-sanctum.php](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/routes/keycloak-sanctum.php).

## Les utilisateurs

À chaque connexion, l'utilisateur est retrouvé grâce à une colonne de la table `users` et à une information (claim) de Keycloak :

```php
// config/keycloak-sanctum.php
'users' => [
    'identifier' => ['column' => 'keycloak_id', 'claim' => 'sub'], // par défaut : identifiant Keycloak
    // 'identifier' => ['column' => 'email', 'claim' => 'email'],  // ou par e-mail
    'auto_create' => env('KEYCLOAK_SANCTUM_AUTO_CREATE_USERS', false),
    'attributes' => [          // colonnes mises à jour à chaque connexion => claim Keycloak
        'name' => 'name',
        'email' => 'email',
    ],
],
```

- Si l'utilisateur n'existe pas et que `auto_create` vaut `false`, la connexion est refusée (`?error=user_not_found`).
- Les claims disponibles sont ceux du jeton Keycloak et de son profil : `sub`, `email`, `name`, `given_name`, `family_name`, `preferred_username`, et vos attributs personnalisés.

## Les rôles

Les rôles sont lus dans le jeton Keycloak, puis traduits en rôles de l'application :

```php
'roles' => [
    'source' => 'client',   // 'client' (rôles du client), 'realm' (rôles du realm) ou 'both'
    'map' => [              // rôle Keycloak => rôle de l'application
        'app-admin' => 'admin',
        'app-editor' => 'editor',
    ],
    'default' => null,      // rôle donné à un utilisateur sans rôle
    'required' => false,    // refuser la connexion sans rôle (?error=no_role)
],
```

Si `map` est vide, les rôles Keycloak sont gardés tels quels. Sinon, seuls les rôles listés sont gardés.

**Protéger des routes :**

```php
Route::middleware(['auth:sanctum', 'keycloak.role:admin,editor'])->group(function () {
    // accessible avec le rôle admin OU editor
});
```

**Lire les rôles dans le code :**

```php
use Kaveraa\KeycloakSanctum\KeycloakSanctum;

KeycloakSanctum::roles();               // ['admin', 'editor']
KeycloakSanctum::hasAnyRole('admin');   // true
```

**Enregistrer les rôles sur l'utilisateur** (table de rôles, paquet de permissions...) : implémentez `SyncsKeycloakRoles` sur le modèle `User`. La méthode est appelée à chaque connexion.

```php
use Kaveraa\KeycloakSanctum\Contracts\SyncsKeycloakRoles;

class User extends Authenticatable implements SyncsKeycloakRoles
{
    public function syncKeycloakRoles(array $roles): void
    {
        $this->syncRoles($roles); // par exemple avec spatie/laravel-permission
    }
}
```

## Déconnexion

- **Depuis le front** : `POST /sso/logout` supprime le jeton et retourne l'adresse de déconnexion Keycloak (voir [Côté front](#côté-front)).
- **Depuis Keycloak** : quand la session se termine dans Keycloak (déconnexion d'une autre application, action d'un administrateur, fin de session), Keycloak appelle `/sso/backchannel-logout` et les jetons de la session sont supprimés.
- **Après inactivité** : avec `KEYCLOAK_SANCTUM_IDLE_TIMEOUT=30`, un jeton inutilisé depuis 30 minutes est refusé. Chaque requête repousse le délai. Seuls les jetons créés par ce paquet sont concernés.
- **Durée maximale** : avec `KEYCLOAK_SANCTUM_TOKEN_EXPIRATION=480`, le jeton expire 8 heures après la connexion, même en cas d'activité.

> **Attention :** l'option d'inactivité utilise `Sanctum::authenticateAccessTokensUsing()`. Si votre application utilise déjà cette fonction, laissez l'option vide et appelez votre propre logique.

## Personnaliser

À placer dans le `boot()` d'un ServiceProvider de l'application :

```php
use Kaveraa\KeycloakSanctum\KeycloakSanctum;

// Retrouver l'utilisateur à votre façon (retourner null refuse la connexion)
KeycloakSanctum::resolveUsersUsing(function (array $claims) {
    return User::firstWhere('email', $claims['email']);
});

// Calculer les rôles à votre façon
KeycloakSanctum::mapRolesUsing(function (array $keycloakRoles, array $claims) {
    return in_array('super-user', $keycloakRoles) ? ['admin'] : ['reader'];
});

// Choisir les données de l'utilisateur envoyées au front (routes /sso/token et /sso/user)
KeycloakSanctum::userPayloadUsing(function ($user, array $roles) {
    return ['id' => $user->id, 'name' => $user->name, 'roles' => $roles];
});
```

Par défaut, l'utilisateur est envoyé avec `$user->toArray()` (les champs de `$hidden` sont masqués) et ses rôles.

## Événements

| Événement | Quand | Données |
|---|---|---|
| `Kaveraa\KeycloakSanctum\Events\KeycloakLogin` | Après une connexion réussie | `$user`, `$roles`, `$claims` |
| `Kaveraa\KeycloakSanctum\Events\KeycloakLogout` | Après une fin de session envoyée par Keycloak | `$sid`, `$sub`, `$revokedTokens` |

## Sécurité

- Les jetons Keycloak sont vérifiés à chaque étape : signature (clés publiques du realm), dates (avec 30 secondes de tolérance), émetteur, et client destinataire.
- Les fins de session envoyées par Keycloak suivent la norme OpenID Connect Back-Channel Logout 1.0 : destinataire, événement, absence de `nonce`, et protection contre le rejeu.
- Le code d'échange est à usage unique, valable 60 secondes, et seule son empreinte est stockée.
- L'`id_token` (utilisé pour la déconnexion Keycloak) est chiffré en base avec la clé de l'application.
- Les clés de Keycloak sont rechargées au plus une fois par minute : de faux jetons ne peuvent pas faire interroger Keycloak en boucle.

Pour signaler une faille, ouvrez une [alerte de sécurité privée](https://github.com/kaveraa/laravel-keycloak-sanctum/security/advisories/new) plutôt qu'une issue publique.

## Développement

```bash
git clone https://github.com/kaveraa/laravel-keycloak-sanctum.git
cd laravel-keycloak-sanctum
composer install
composer test
```

Les tests utilisent un faux serveur Keycloak : de vraies clés RSA et de vrais jetons signés, sans serveur à installer.

Pour proposer une modification, lisez le guide [CONTRIBUTING.md](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/CONTRIBUTING.md). Voir le [CHANGELOG](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/CHANGELOG.md) pour l'historique des versions.

## Licence

MIT. Voir [LICENSE](https://github.com/kaveraa/laravel-keycloak-sanctum/blob/main/LICENSE).

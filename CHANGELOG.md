# Changelog

**FR** Toutes les évolutions notables du paquet sont listées ici. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

**EN** All important changes of the package are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [semantic versioning](https://semver.org/).

## [1.0.0] - 2026-09-25

### Ajouté / Added

- **FR** Connexion Keycloak pour une application front avec une API Laravel : redirection, retour, puis échange d'un code à usage unique contre un jeton Sanctum.
  **EN** Keycloak login for a front-end application with a Laravel API: redirect, callback, then exchange of a one-time code for a Sanctum token.
- **FR** Vérification des jetons Keycloak (signature, dates, émetteur, destinataire), clés gardées en cache et rechargées quand Keycloak en change.
  **EN** Keycloak token checks (signature, dates, issuer, audience), keys cached and reloaded when Keycloak changes them.
- **FR** Utilisateurs retrouvés ou créés à la connexion, colonne d'identification et attributs configurables.
  **EN** Users found or created at login, with a configurable id column and attributes.
- **FR** Rôles lus dans le jeton (realm, client ou les deux), table de correspondance, rôle par défaut, middleware `keycloak.role`.
  **EN** Roles read from the token (realm, client or both), mapping table, default role, `keycloak.role` middleware.
- **FR** Déconnexion depuis le front, depuis Keycloak (back-channel logout) et après inactivité.
  **EN** Logout from the front-end, from Keycloak (back-channel logout) and after inactivity.
- **FR** Commande `keycloak-sanctum:install`, événements `KeycloakLogin` et `KeycloakLogout`, points de personnalisation.
  **EN** `keycloak-sanctum:install` command, `KeycloakLogin` and `KeycloakLogout` events, customization hooks.

[1.0.0]: https://github.com/kaveraa/laravel-keycloak-sanctum/releases/tag/v1.0.0

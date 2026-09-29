# Contribuer / Contributing

**Français** - [English](#english)

## Français

Merci de votre aide ! Toute modification passe par une **Pull Request** : la branche `main` est protégée et la CI doit être verte pour fusionner.

### 1. Préparer le projet

```bash
git clone https://github.com/kaveraa/laravel-keycloak-sanctum.git
cd laravel-keycloak-sanctum
composer install
```

Il faut PHP 8.2 ou plus, avec les extensions `openssl` et `pdo_sqlite`.

### 2. Créer une branche

```bash
git checkout -b fix/nom-court-du-changement
```

Préfixes conseillés : `feat/` (nouveauté), `fix/` (correction), `docs/` (documentation).

### 3. Lancer les tests

```bash
composer test
```

Les tests n'ont pas besoin d'un vrai Keycloak : `tests/Support/FakeKeycloak.php` génère de vraies clés RSA, signe de vrais jetons et sert la configuration OpenID avec `Http::fake()`.

### 4. Règles du projet

- **Tests** : toute correction ou nouveauté est accompagnée d'un test.
- **Sécurité** : toute donnée venant de Keycloak ou du front est vérifiée avant usage. En cas de doute, refusez.
- **Documentation** : mettez à jour `README.md` (anglais simple) **et** `README.fr.md` (français), ainsi que le `CHANGELOG.md` (section en haut, en français et en anglais).
- **Commits** : en anglais simple, compréhensible par un débutant. Phrases courtes, pas de jargon.
- **Caractères** : uniquement des caractères du clavier dans les fichiers et les commits : `-` (pas de tiret long), `"` (pas de guillemets français), `->` (pas de flèche), pas d'emoji ni d'icône. Les lettres accentuées du français sont acceptées.

### 5. Ouvrir la Pull Request

Poussez votre branche, ouvrez une PR vers `main` et remplissez la checklist proposée. La PR peut être fusionnée quand le contrôle **All tests passed** est vert.

### Publier une version (mainteneur)

Après la fusion : mettre à jour le `CHANGELOG.md`, puis créer un tag `vX.Y.Z` sur `main`. Packagist publie la version automatiquement.

---

## English

Thank you for your help! Every change goes through a **Pull Request**: the `main` branch is protected, and the CI must be green before merge.

### 1. Set up the project

```bash
git clone https://github.com/kaveraa/laravel-keycloak-sanctum.git
cd laravel-keycloak-sanctum
composer install
```

You need PHP 8.2 or more, with the `openssl` and `pdo_sqlite` extensions.

### 2. Create a branch

```bash
git checkout -b fix/short-name-of-the-change
```

Suggested prefixes: `feat/` (new feature), `fix/` (bug fix), `docs/` (documentation).

### 3. Run the tests

```bash
composer test
```

The tests do not need a real Keycloak: `tests/Support/FakeKeycloak.php` creates real RSA keys, signs real tokens and serves the OpenID configuration with `Http::fake()`.

### 4. Project rules

- **Tests**: every fix or new feature comes with a test.
- **Security**: every value that comes from Keycloak or from the front-end is checked before use. If in doubt, refuse.
- **Documentation**: update `README.md` (simple English) **and** `README.fr.md` (French), and the `CHANGELOG.md` (section at the top, in French and English).
- **Commits**: in simple English, easy to read for a beginner. Short sentences, no jargon.
- **Characters**: only keyboard characters in files and commits: `-` (no long dash), `"` (no French quotes), `->` (no arrow), no emoji or icon. French accented letters are fine.

### 5. Open the Pull Request

Push your branch, open a PR to `main` and fill in the checklist. The PR can be merged when the **All tests passed** check is green.

### Release a version (maintainer)

After the merge: update the `CHANGELOG.md`, then create a `vX.Y.Z` tag on `main`. Packagist publishes the version automatically.

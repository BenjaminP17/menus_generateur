# CLAUDE.md

Instructions de travail pour Claude Code dans ce dépôt.

## Contexte

Application web de génération de menus hebdomadaires (déjeuner et dîner) à partir d'une base de plats saisie par l'utilisateur, en tenant compte de la saison et du rythme semaine / week-end. Un seul foyer, protégé par un compte unique (pas d'inscription, pas de gestion de plusieurs profils). Le détail des besoins et des règles métier est dans PRD.md (version 0.2, brouillon : les points marqués « À valider » ne sont pas tranchés).

## Stack technique

- Backend : API JSON en PHP 8.2 ou plus récent, sur Symfony 7.4 LTS (squelette minimal, `symfony/skeleton`, sans API Platform). Les règles métier (saisons, semaine / week-end, variété) vivent côté PHP, dans des classes PHP pures indépendantes du framework. Accès à la base : PDO direct, sans Doctrine. `config.platform.php` fixé à `8.2` dans `composer.json`.
- Frontend : JavaScript vanilla, modules ES natifs (`type="module"`), sans build ni framework. Un module `api.js` centralise les appels `fetch`. Éviter `innerHTML` avec des données utilisateur.
- Persistance : SQLite via PDO. Le fichier de base est hors du dossier public et n'est jamais commité.
- Hébergement : o2switch (PHP 8.3 ou 8.4 sur le site web, `pdo_sqlite` requis). La racine web pointe sur `public/` ; le fichier SQLite reste hors de `public/`. Vérifier la version PHP du site dans cPanel, qui peut différer de la CLI.

## Commandes

- Installation : `composer install`
- Lancement local : `php -S localhost:8000 -t public`, puis `/api/health`
- Tests : `vendor/bin/phpunit`
- Analyse statique : `vendor/bin/phpstan analyse` (configuration dans `phpstan.dist.neon`)
- Style : `vendor/bin/php-cs-fixer fix` (configuration dans `.php-cs-fixer.dist.php`)
- Conteneur et routes : `php bin/console lint:container`, `php bin/console debug:router`
- Secrets locaux : `.env.local` (non versionné), contient notamment `APP_SECRET`, `APP_AUTH_USER` et `APP_AUTH_PASSWORD_HASH` (compte unique, jalon 1b)
- Calcul du hash du mot de passe du compte : `php bin/console app:auth:hash-password`

## Règles métier (source : PRD §5.4)

Ces règles sont le cœur du produit ; le générateur doit les respecter et signaler une base insuffisante plutôt que les violer.

- Saisons : printemps-été = mars à août, automne-hiver = septembre à février, hémisphère nord. Un plat est printemps-été, automne-hiver ou hybride. Saison déduite de la date de référence (par défaut la date du jour).
- Semaine / week-end : lundi à vendredi = plats « rapides » en priorité ; samedi et dimanche = plats « élaborés ». Le niveau est choisi à la main par l'utilisateur pour chaque plat, jamais calculé.
- Variété : aucun plat identique dans la même semaine ; proportion de repas végétariens selon le réglage (aucun / quelques-uns / majoritairement).
- Un menu contient exactement le nombre de déjeuners et de dîners demandé.
- Régénérer un repas ne modifie pas les autres repas.
- Pas de génération de plats par un modèle de langage : la base est saisie manuellement.

## Conventions

- Langue des commentaires, des messages de commit et de la documentation : français. Identifiants de code (variables, fonctions, classes) en anglais. Interface et contenu de l'application en français.
- API : chemins, champs des payloads et messages en français, alignés sur les colonnes de la base ; les classes et propriétés PHP restent en anglais. La traduction vit dans `src/Controller/DishPayload.php`, pour que `src/Menu/` ignore le vocabulaire HTTP. Contrat complet dans `doc/api.md`.
- Toute erreur d'API utilise la forme `{"erreur": "...", "champs": {"<champ>": "..."}}`, construite par `src/Controller/ApiError.php` ; `champs` n'apparaît que pour les erreurs rattachées à un champ.
- Style de code : `@Symfony` via php-cs-fixer, phpstan niveau 5 sur `src/` et `tests/`.
- Organisation des dossiers :
  - `src/` : code PHP (API Symfony) ; `src/Controller/` pour les endpoints, `src/Database/` pour la connexion PDO et les dépôts, `src/Menu/` pour les classes PHP pures du domaine (enums, `Dish`, validation, horloge) réutilisées par le générateur.
  - `config/` : configuration Symfony ; `config/services.yaml` fixe le chemin de la base.
  - `public/` : racine web. Le frontend (modules ES, `public/js/api.js` centralise les `fetch`) est servi tel quel depuis `public/`, même origine, sans build.
  - `var/` : cache et base SQLite (`var/data/menus.sqlite`), jamais versionnés.
  - `tests/` : tests PHPUnit.

## Points de vigilance

- Ne pas coder sur une règle marquée « À valider » dans le PRD sans en parler à l'utilisateur ; les questions ouvertes du PRD §7 doivent d'abord être tranchées.
- Hors périmètre V1 : enregistrement des menus (le journal `proposition` des plats proposés, sans menu, est prévu : voir `doc/modelisation-bdd.md`), comptes multiples, plusieurs profils, nombre de personnes, liste de courses, recettes détaillées, nutrition, allergies, jours de fête. Ne pas les ajouter.
- Authentification (jalon 1b, SEC-01) : un compte unique, ses identifiants dans `.env.local`, vérifié par `App\Security\Credentials` et gardé par `App\EventListener\AuthenticationListener`. Volontairement pas de table `utilisateur` ni de lien avec `plat` ou `proposition` : un seul foyer, donc rien à distinguer. Ne pas ajouter de table tant qu'il n'y a qu'un compte.
- Ne pas committer de données personnelles ni de base de plats réelle ; pas de secrets dans le dépôt. Les plats de test sont fictifs (`tests/Support/DishFactory.php`).
- `src/Menu/DishNameNormalizer.php` est le seul point qui calcule `nom_normalise` : insertion, modification, détection de doublon et tri doivent passer par lui, sinon l'unicité des noms devient incohérente.
- Les dépôts reçoivent une connexion `\PDO` (service défini dans `config/services.yaml`), et non `Database`, pour rester testables sur une base en mémoire.
- Les booléens écrits en base passent par un cast `(int)` : PDO lierait `false` en chaîne vide, que les `CHECK` de SQLite refusent.

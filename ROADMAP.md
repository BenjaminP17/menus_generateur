# ROADMAP : menus_generator

Feuille de route de développement vers la V1. Le détail des besoins et des règles métier est dans [PRD.md](PRD.md). Le modèle de données est dans [doc/modelisation-bdd.md](doc/modelisation-bdd.md).

Principe : on construit de bas en haut (données, puis règles métier pures et testées, puis API, puis interface). Chaque jalon doit être utilisable et testable seul.

## Jalon 0 : Fondations de données

- [x] Fichier `config/schema.sql` (DDL de `doc/modelisation-bdd.md`) avec les 8 catégories de référence
- [x] `SchemaInstaller` dans `src/Database/`
- [x] Commande console `app:db:init`, idempotente
- [x] Tests : schéma installé sur SQLite en mémoire ; `CHECK` et unicité de `nom_normalise` vérifiés

## Jalon 1 : Gestion de la base de plats (PRD §5.1)

- [x] Enums PHP (`Season`, `DishType`, `Level`, `MealKind`) et objet `Dish` dans `src/Menu/`
- [x] `DishRepository` (PDO) : lister, lire, créer, modifier, supprimer ; `nom_normalise` calculé en PHP (minuscules, sans accents)
- [x] `GET/POST /api/plats`, `GET/PUT/DELETE /api/plats/{id}`, `GET /api/categories`
- [x] Validation : champs obligatoires, doublon de nom (409), au moins un repas autorisé
- [x] Tests PHPUnit du repository et des contrôleurs

Contrat de l'API : [doc/api.md](doc/api.md).

## Jalon 1b : Authentification (SEC-01)

Décidé après l'audit du 2026-10-26 (voir `doc/audit-securite.md`) : un compte unique protège l'API, configuré hors dépôt, sans table `utilisateur` (un seul foyer, rien à distinguer). Session maison, sans `symfony/security-bundle`, cohérente avec le reste du projet (squelette minimal, PDO direct).

- [x] `APP_AUTH_USER` / `APP_AUTH_PASSWORD_HASH` dans `.env.local` ; commande `app:auth:hash-password` pour calculer le hash
- [x] `App\Security\Credentials` : vérifie l'identifiant et le mot de passe (`password_verify`)
- [x] `App\EventListener\AuthenticationListener` : bloque `/api/*` sans session active, sauf `/api/health` et `/api/session`
- [x] `GET/POST/DELETE /api/session` : état de la connexion, connexion, déconnexion
- [x] Limitation des tentatives de connexion (`symfony/rate-limiter`, 5 tentatives / 15 min par IP)
- [ ] Cookie de session : `SameSite=Strict` (vérifié), attribut `Secure` à vérifier en HTTPS sur o2switch
- [x] Tests : accès bloqué sans session, connexion/déconnexion, limiteur, routes exemptées

## Jalon 2 : Frontend de gestion des plats

- [ ] `public/index.html`, `public/js/api.js` (seul module qui appelle `fetch`), modules ES par écran
- [ ] Écran de connexion (identifiant / mot de passe), redirection sur 401, bouton de déconnexion
- [ ] Liste des plats et formulaire d'ajout, de modification et de suppression (avec confirmation)
- [ ] Pas d'`innerHTML` avec des données utilisateur
- [ ] Mise en page responsive (ordinateur et téléphone), navigation au clavier, focus visible
- [ ] Respecter `doc/design.md` : palette, composants, écrans, ton (tutoiement) et règles d'accessibilité
- [ ] Livrable : l'utilisateur peut se connecter et constituer sa base

## Jalon 3 : Cœur du générateur (PRD §5.4)

Classes PHP pures dans `src/Menu/`, sans accès à la base ni au framework. Le générateur reçoit une liste de `Dish` et un tirage aléatoire injectable, pour des tests déterministes.

- [ ] `SeasonResolver` : date vers saison (mars à août : printemps-été ; septembre à février : automne-hiver)
- [ ] `MenuRequest` et sa validation (1 à 7 déjeuners, 1 à 7 dîners, réglage végétarien, date de référence)
- [ ] Filtrage dur : repas autorisé, saison courante ou hybride, saison opposée exclue, aucun plat en double dans la semaine
- [ ] Priorités douces : niveau selon le jour (rapide du lundi au vendredi, élaboré le week-end, repli signalé) ; 30 à 40 % d'hybrides ; quota végétarien (aucun, au plus 1 sur 7 jours, au moins 8 sur 14) ; catégorie principale différente entre déjeuner et dîner du même jour
- [ ] Tirage pondéré selon `poids` (le facteur de fraîcheur arrive au jalon 5)
- [ ] `BaseDiagnostic` : indique par catégorie si la base suffit pour une semaine complète
- [ ] Test d'invariant : le menu contient exactement le nombre demandé de déjeuners et de dîners

## Jalon 4 : API de génération et écran menu

- [ ] `POST /api/menu` : génère un menu
- [ ] `POST /api/menu/regenerer` : régénère un repas, ou tout le menu, sans toucher aux autres repas
- [ ] Choix libre d'un plat existant pour un repas, avec avertissement non bloquant si une règle n'est pas respectée
- [ ] Frontend : formulaire de réglages, planning sur 7 jours, listes déjeuner et dîner, actions « régénérer ce repas », « choisir un plat », « tout régénérer », affichage des avertissements
- [ ] Menu conservé côté navigateur (rien n'est stocké côté serveur à ce stade)
- [ ] Performance : génération en moins de 3 s avec 200 plats

## Jalon 5 : Journal `proposition` et fraîcheur

Optionnel pour une livraison plus rapide, mais le PRD §5.4 demande la règle de fraîcheur.

- [ ] Écriture dans `proposition` à la validation du menu (décidé) : bouton « Valider le menu » et endpoint associé
- [ ] Une nouvelle validation remplace les lignes de la semaine concernée
- [ ] Pénalité de fraîcheur dans le tirage (plat récent moins tiré, plat jamais proposé favorisé)
- [ ] Purge des propositions de plus de 8 semaines

## Jalon 6 : Finition et mise en production (o2switch)

- [ ] Accessibilité WCAG 2.1 AA : clavier, contrastes, libellés, annonces d'état
- [ ] Messages d'erreur côté interface : API indisponible, base vide (renvoi vers la saisie des plats)
- [ ] Déploiement : racine web sur `public/`, fichier SQLite hors de `public/`, version PHP du site vérifiée dans cPanel (8.3 ou 8.4), `APP_SECRET` dans `.env.local`, `composer install --no-dev`, `app:db:init` à l'installation
- [ ] Compléter `README.md` (description, déploiement)
- [ ] Audit de sécurité léger avant mise en ligne

## Questions à trancher en cours de route

- **Force de la pénalité de fraîcheur et durée de rétention** (jalon 5) : 8 semaines proposées.
- **Suppression d'un plat présent dans un menu affiché** (PRD §5.1) : tranché au jalon 1. La suppression est libre et sans condition (204), l'historique du plat part en cascade, et le menu affiché dans le navigateur reste inchangé puisqu'aucun menu n'est stocké côté serveur.
- **Données de test** : uniquement des plats fictifs, dans les fixtures de tests. Aucune base de plats réelle n'est commitée.

## Vérification à chaque jalon

- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse`
- `vendor/bin/php-cs-fixer fix --dry-run`
- `php bin/console lint:container`
- Jalons 1, 1b et 4 : `php -S localhost:8000 -t public`, puis appels sur `/api/plats`, `/api/session` et `/api/menu`
- Jalons 2, 4 et 6 : test manuel dans le navigateur, sur ordinateur et téléphone, clavier seul
- Critères de succès du PRD §3 vérifiés par des tests sur le générateur, avec une base de 200 plats fictifs (saison, niveau, absence de doublon, nombre exact de repas, temps de génération)

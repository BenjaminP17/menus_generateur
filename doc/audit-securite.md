# Historique des audits de sécurité : menus_generator

Ce document suit dans le temps les audits de sécurité de l'application (voir le skill
`audit-securite`). Règle de tenue : un nouvel audit ajoute une **nouvelle entrée datée** en bas
du fichier ; il ne réécrit jamais les constats d'un audit passé. S'il confirme, contredit ou
complète un constat antérieur, il le dit explicitement dans sa propre entrée, et seule la case de
suivi ci-dessous est mise à jour.

## Suivi des corrections

Case cochée = corrigé et **revérifié par un audit ultérieur**. Corriger le code sans le
revérifier ne suffit pas à cocher la case.

- [ ] **SEC-01** — Élevée — Aucun contrôle d'accès sur l'API (lecture/écriture/suppression) — ouvert depuis l'audit du 2026-10-26
- [ ] **SEC-02** — Moyenne — Identifiant de route hors bornes → 500 révélant un chemin serveur — ouvert depuis l'audit du 2026-10-26
- [ ] **SEC-03** — Moyenne — `APP_ENV=prod` absent de la checklist de déploiement (jalon 6) — ouvert depuis l'audit du 2026-10-26
- [ ] **SEC-04** — Faible — En-têtes de sécurité HTTP absents (CSP, X-Content-Type-Options, X-Frame-Options...) — ouvert depuis l'audit du 2026-10-26
- [ ] **SEC-05** — Faible — Aucune limite de taille ni de débit sur les écritures de l'API — ouvert depuis l'audit du 2026-10-26
- [ ] **SEC-06** — Info — Secret de test par défaut du squelette Symfony — sans impact réel, aucune action requise ; case gardée pour mémoire, à cocher directement

## Audits réalisés

| Date | Commit audité | Findings | Rapport |
|---|---|---|---|
| 2026-10-26 | `66bdf65` (branche `jalon-1-base-de-plats`) | 0 Critique · 1 Élevée · 3 Moyennes · 2 Faibles · 1 Info | voir ci-dessous |

---

## Audit du 2026-10-26

- **Commit audité** : `66bdf65` (branche `jalon-1-base-de-plats`)
- **Stack détectée** : PHP 8.2, Symfony 7.4 (squelette minimal, sans API Platform ni Doctrine), PDO/SQLite, pas de frontend encore livré (jalon 2)
- **Exposition** : application non encore déployée. Prévue pour un seul foyer, sans compte, sur hébergement o2switch (voir CLAUDE.md). Données : une base de plats (noms de recettes) — pas de données personnelles au sens strict, mais c'est l'unique actif métier de l'application.
- **Méthode** : statique (revue de code complète de `src/`, `config/`, `public/`, scan de secrets, audit des dépendances) + dynamique (serveur local `php -S localhost:8000`, en-têtes, méthodes HTTP, cas limites)

### 1. Synthèse

Le code du jalon 1 est propre sur les points qui se codent bien : requêtes préparées partout, validation stricte par liste blanche, aucun secret réellement commité, dépendances saines. Le risque principal n'est pas dans le code métier mais dans ce qui n'existe pas encore : **aucun contrôle d'accès**, ce qui est un choix de périmètre du PRD (« un seul foyer, sans compte ») mais qui devient un risque réel le jour où l'application sera jointe sur internet sans protection complémentaire. Un deuxième point, confirmé par un test dynamique, est un identifiant de route trop long qui provoque une erreur 500 révélant le chemin absolu du serveur.

| Critique | Élevée | Moyenne | Faible | Info |
|---|---|---|---|---|
| 0 | 1 | 3 | 2 | 1 |

**Trois actions prioritaires**
1. Décider comment protéger l'accès avant toute mise en ligne sur o2switch (SEC-01) — authentification HTTP basique au niveau du serveur web est le choix le plus simple, compatible avec « pas de compte ».
2. Corriger la contrainte de route `{id}` pour qu'un entier hors bornes renvoie un 404 propre au lieu d'un 500 qui expose un chemin serveur (SEC-02).
3. Ajouter `APP_ENV=prod` (et vérifier `APP_DEBUG` à faux) à la checklist du jalon 6, avant tout déploiement (SEC-03).

### 2. Plan d'action

| Priorité | Finding | Action | Effort |
|---|---|---|---|
| P0 (avant mise en ligne) | SEC-01 | Choisir et poser une protection d'accès (Basic Auth `.htaccess`, ou équivalent) | M |
| P0 (avant mise en ligne) | SEC-03 | `APP_ENV=prod` explicite en production, vérifier `APP_DEBUG=0` | S |
| P1 (ce sprint) | SEC-02 | Borner `{id}` dans les routes, renvoyer un 404 propre | S |
| P1 (ce sprint) | SEC-04 | En-têtes de sécurité HTTP (CSP, X-Content-Type-Options, X-Frame-Options) | S |
| P2 (backlog) | SEC-05 | Limite de taille/débit sur les écritures de l'API | S |
| P2 (backlog) | SEC-06 | `.env.test` : secret de test par défaut du squelette, sans impact réel | — |

Effort : S = moins d'une heure, M = une demi-journée, L = plus d'une journée.

### 3. Findings détaillés

#### SEC-01 : Aucun contrôle d'accès sur une API complète de lecture/écriture/suppression

- **Sévérité** : Élevée (deviendrait Critique le jour du déploiement sans mitigation, puisque l'impact serait alors la compromission totale de l'unique actif de données de l'application)
- **Catégorie** : OWASP A01:2021 – Broken Access Control (absence totale de contrôle d'accès, pas une faille dans un contrôle existant)
- **Statut de la preuve** : confirmé (lu dans le code et la configuration)
- **Où** : `config/services.yaml`, `composer.json` (aucun bundle d'authentification), tous les contrôleurs de `src/Controller/`
- **Preuve** : `composer.json` ne liste ni `symfony/security-bundle` ni aucun mécanisme d'authentification. Aucun fichier `config/packages/security.yaml`. Les six endpoints de `DishController` et `CategoryController` sont appelables sans aucune vérification d'identité, par quiconque atteint l'URL.
- **Impact** : c'est un choix de périmètre explicite du PRD (« un seul foyer, sans compte ») — pas un oubli. Mais une fois déployé sur o2switch tel quel, n'importe qui qui trouve ou devine l'URL peut lister, créer, modifier et supprimer tous les plats, sans laisser de trace (pas de comptes, donc pas d'attribution). C'est la seule base de données de l'application : sa perte ou sa corruption est la pire conséquence possible pour ce produit.
- **Correctif** — ce point est une décision produit, pas quelque chose à corriger silencieusement (CLAUDE.md). Trois options compatibles avec « pas de compte » :

```apache
# public/.htaccess — authentification HTTP basique, un seul mot de passe partagé,
# sans système de comptes. Le fichier .htpasswd reste hors du dépôt et hors de public/.
AuthType Basic
AuthName "menus_generator"
AuthUserFile /home/<utilisateur_o2switch>/.htpasswd
Require valid-user
```

  Alternatives : restriction par adresse IP si le foyer a une IP fixe (`Require ip <adresse>` dans le même bloc), ou un jeton partagé vérifié par un listener Symfony (`kernel.request`) si l'authentification HTTP ne convient pas pour l'usage au téléphone.
- **Pourquoi ce correctif** : une authentification HTTP basique ne demande ni compte ni session, se configure au niveau du serveur web (donc avant même que Symfony ne s'exécute), et correspond exactement à « un seul foyer, un seul mot de passe ».
- **Effort** : M (configuration + mot de passe à distribuer aux membres du foyer)

#### SEC-02 : Identifiant de route hors bornes → 500 révélant le chemin absolu du serveur

- **Sévérité** : Moyenne
- **Catégorie** : OWASP A05:2021 – Security Misconfiguration (messages d'erreur détaillés) ; cause sous-jacente : CWE-20, validation insuffisante du paramètre de route
- **Statut de la preuve** : confirmé (observé dynamiquement sur `localhost:8000`)
- **Où** : `src/Controller/DishController.php:55,65,84` (`requirements: ['id' => '\d+']`)
- **Preuve** :
  ```
  GET /api/plats/99999999999999999999  →  500 Internal Server Error

  App\Controller\DishController::show(): Argument #1 ($id) must be of type int,
  string given, called in /home/benjamin/Projets/menus_generator/vendor/symfony/
  http-kernel/HttpKernel.php on line 185
  ```
  Reproduit sur `show`, `update` et `delete` (les trois routes `/api/plats/{id}`). Le seuil exact : un entier de 19 chiffres (jusqu'à `PHP_INT_MAX` = 9223372036854775807) passe en 404 normal ; 20 chiffres ou plus déclenche la 500. La regex `\d+` ne borne pas le nombre de chiffres, donc la conversion automatique en `int` par Symfony échoue au-delà de la capacité d'un entier PHP.
- **Impact** : une requête triviale, sans authentification requise (voir SEC-01), révèle le chemin absolu du projet sur le serveur (nom du compte système, structure des dossiers, chemin complet vers `vendor/`). C'est une information de reconnaissance utile à un attaquant, pas une fuite de données en soi — mais elle ne devrait jamais apparaître en production.
- **Correctif** :

```php
// src/Controller/DishController.php — borne le nombre de chiffres à ceux que PHP_INT_MAX
// peut représenter sur une plateforme 64 bits (19 chiffres), pour qu'un entier hors bornes
// soit un 404 normal plutôt qu'une erreur de type non interceptée.
#[Route('/api/plats/{id}', name: 'api_plats_show', methods: ['GET'], requirements: ['id' => '\d{1,18}'])]
```

  Et, en complément indépendant de ce correctif (voir SEC-03), s'assurer qu'aucun message d'erreur de ce type ne puisse jamais apparaître en production, même pour une cause imprévue :

```yaml
# config/packages/prod/framework.yaml (nouveau fichier)
when@prod:
    framework:
        router:
            strict_requirements: null
```

- **Pourquoi ce correctif** : `\d{1,18}` exclut les entiers qui dépasseraient la capacité de `int` sur une plateforme 64 bits avant même que la route ne corresponde, donc le routeur renvoie lui-même un 404 — exactement le comportement déjà voulu pour un identifiant inexistant.
- **Effort** : S

#### SEC-03 : Le mode debug s'active par défaut, `APP_ENV=prod` n'est pas dans la checklist de déploiement

- **Sévérité** : Moyenne
- **Catégorie** : OWASP A05:2021 – Security Misconfiguration
- **Statut de la preuve** : confirmé (lu dans la configuration, et c'est la cause racine de la verbosité observée en SEC-02)
- **Où** : `.env`, `.env.dev`, `.env.test`, `ROADMAP.md` (jalon 6)
- **Preuve** : aucun des trois fichiers `.env*` ne définit `APP_DEBUG`. Le composant Symfony Runtime active le mode debug dès que `APP_ENV` n'est pas explicitement `prod`. La checklist du jalon 6 dans `ROADMAP.md` liste la vérification de la version PHP, `APP_SECRET`, `composer install --no-dev` et `app:db:init`, mais pas `APP_ENV=prod`.
- **Impact** : si le déploiement sur o2switch oublie de positionner `APP_ENV=prod` dans `.env.local`, l'application tourne en production avec les pages d'erreur détaillées de Symfony (traces, chemins de fichiers, parfois extraits de requêtes) visibles par n'importe quel visiteur — comme démontré en SEC-02.
- **Correctif** :

```bash
# .env.local sur le serveur de production (jamais commité)
APP_ENV=prod
```

  Et dans `ROADMAP.md`, jalon 6 :
  ```diff
  - Déploiement : racine web sur `public/`, fichier SQLite hors de `public/`, version PHP du
    site vérifiée dans cPanel (8.3 ou 8.4), `APP_SECRET` dans `.env.local`,
  + Déploiement : racine web sur `public/`, fichier SQLite hors de `public/`, version PHP du
    site vérifiée dans cPanel (8.3 ou 8.4), `APP_ENV=prod` et `APP_SECRET` dans `.env.local`,
    `composer install --no-dev`, `app:db:init` à l'installation
  ```
- **Pourquoi ce correctif** : c'est la variable qui pilote tout le reste (debug, verbosité des erreurs, cache) ; l'inscrire explicitement dans la checklist évite qu'elle soit oubliée par automatisme du squelette Symfony.
- **Effort** : S

#### SEC-04 : En-têtes de sécurité HTTP absents

- **Sévérité** : Faible
- **Catégorie** : OWASP A05:2021 – Security Misconfiguration
- **Statut de la preuve** : confirmé (observé dynamiquement)
- **Où** : toute réponse HTTP de l'application (aucune configuration d'en-têtes trouvée)
- **Preuve** (`check-headers.sh` sur `localhost:8000`) :
  ```
  [WARN] Content-Security-Policy absent
  [WARN] X-Content-Type-Options: nosniff absent
  [WARN] Pas de protection contre le clickjacking (X-Frame-Options / frame-ancestors)
  [WARN] Referrer-Policy absent
  [WARN] X-Powered-By révèle la technologie : PHP/8.2.35
  ```
- **Impact** : limité aujourd'hui (API JSON pure, pas encore de HTML servant du contenu utilisateur), mais ces en-têtes sont la défense en profondeur attendue dès que le frontend du jalon 2 servira des pages HTML depuis `public/`. `X-Powered-By` facilite la reconnaissance en annonçant la version exacte de PHP.
- **Correctif** — le plus simple sur hébergement partagé Apache (o2switch) est au niveau du serveur, pas du code PHP :

```apache
# public/.htaccess
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set Referrer-Policy "same-origin"
Header always set Content-Security-Policy "default-src 'self'"
Header unset X-Powered-By
```

  `Header unset X-Powered-By` ne masque pas toujours l'en-tête ajouté par le SAPI PHP lui-même : si cPanel le permet, positionner `expose_php = Off` dans la configuration PHP du site est plus fiable.
- **Pourquoi ce correctif** : centralise la politique de sécurité au niveau du serveur web, sans toucher au code applicatif, et reste valable même si une page HTML est ajoutée plus tard.
- **Effort** : S

#### SEC-05 : Aucune limite de taille ni de débit sur les écritures de l'API

- **Sévérité** : Faible (dans le contexte actuel : un seul foyer, pas encore déployé ; deviendrait plus pertinent si SEC-01 n'est pas traité)
- **Catégorie** : OWASP API Top 10 – API4:2023 Unrestricted Resource Consumption
- **Statut de la preuve** : probable (lu dans le code, pas testé en charge — un test de volumétrie réelle sort du périmètre défensif de cet audit)
- **Où** : `src/Controller/DishController.php::readInput()`, aucune configuration de taille de requête
- **Preuve** : `Request::toArray()` décode l'intégralité du corps avant toute validation. Le champ `nom` n'est borné à 120 caractères qu'après ce décodage (`DishInputValidator::validateName`). Aucun paquet de limitation de débit (`symfony/rate-limiter` absent de `composer.json`).
- **Impact** : sans authentification (SEC-01), un visiteur pourrait envoyer un grand nombre de requêtes de création, ou des corps JSON volumineux, sans frein applicatif — consommation de mémoire et de temps CPU.
- **Correctif** : à ce stade, le plus rentable est de fixer une limite de taille de requête au niveau du serveur (`LimitRequestBody` en Apache, ou `post_max_size` en PHP) plutôt que d'ajouter du code. Si l'application reste accessible sans authentification, `symfony/rate-limiter` sur les routes d'écriture serait la suite logique.
- **Effort** : S

#### SEC-06 : Secret de test par défaut du squelette Symfony

- **Sévérité** : Info
- **Catégorie** : Secrets et configuration
- **Statut de la preuve** : confirmé, mais sans impact réel
- **Où** : `.env.test:3` — `APP_SECRET='$ecretf0rt3st'`
- **Preuve** : valeur par défaut générée par le squelette `symfony/skeleton` pour l'environnement de test, committée comme à l'accoutumée dans tous les projets Symfony.
- **Impact** : aucun — `APP_SECRET` n'est utilisé qu'en environnement `test`, jamais atteignable depuis l'extérieur, et cette valeur est strictement identique dans des milliers de projets Symfony publics. Ce n'est pas un secret de production.
- **Correctif** : aucun nécessaire. Mentionné uniquement pour que le scan de secrets ne laisse pas un candidat sans explication.
- **Effort** : —

### 4. Dépendances

| Paquet | Version | Vulnérabilité | Gravité | Atteignable ? | Version corrigée |
|---|---|---|---|---|---|
| — | — | `composer audit` : aucune vulnérabilité connue | — | — | — |

11 paquets de production (Symfony 7.4.*, aucune extension tierce hors écosystème Symfony), 3 paquets de développement (phpunit, phpstan, php-cs-fixer). Base de dépendances volontairement réduite (pas de Doctrine, pas d'API Platform), ce qui limite mécaniquement la surface de vulnérabilités tierces.

### 5. Secrets

Aucun secret actif trouvé. Deux candidats examinés et écartés :
- `config/packages/framework.yaml:3` : `secret: '%env(APP_SECRET)%'` — référence à une variable d'environnement, pas une valeur en dur.
- `.env.test` : voir SEC-06, secret de test par défaut sans portée en production.

`.env.local` (qui porterait le vrai `APP_SECRET` de production) n'est pas suivi par Git, conformément au `.gitignore` et au README. Historique Git (6 commits) sans motif à forte confiance détecté.

### 6. Points positifs

- **Aucune injection SQL possible** : toutes les requêtes de `src/Database/` passent par des requêtes préparées avec paramètres nommés ; les seuls fragments de SQL construits dynamiquement (listes de colonnes dans `DishRepository::create()`/`update()`) proviennent de clés de tableau fixes et codées en dur, jamais de données utilisateur.
- **Validation stricte par liste blanche** : `DishInputValidator` vérifie le type exact de chaque champ (`is_string`, `is_bool`, `is_int`) et les valeurs d'énumération via `tryFrom()`, sans conversion permissive ; aucun risque d'assignation de masse, les champs sont lus un par un par leur nom.
- **Normalisation centralisée** : un seul point (`DishNameNormalizer`) calcule la clé d'unicité, évitant les incohérences qu'une duplication de logique aurait pu introduire.
- **Format d'erreur sans fuite** : `ApiError` ne renvoie jamais de trace ni de détail interne, seulement un message et les champs en erreur.
- **Dépendances saines et minimales** : `composer audit` propre, pas de framework ORM ni de bibliothèque tierce superflue.
- **Hygiène Git correcte** : `.gitignore` exclut effectivement les fichiers `.sqlite`, `.env.local` et tout `var/` (confirmé y compris pour le fichier de plats personnel de l'utilisateur, `var/import/plats.json`), aucun de ces fichiers n'apparaît dans l'historique.
- **CORS implicitement sûr** : aucune politique CORS n'est configurée, donc le comportement par défaut du navigateur (même origine uniquement) s'applique — confirmé dynamiquement.
- **Suppression cohérente avec le modèle de données** : `ON DELETE CASCADE` sur `proposition` évite les lignes orphelines, et la suppression libre d'un plat (décidée au jalon 1) ne crée pas d'état incohérent.

### 7. Périmètre vérifié

- [x] Scan de secrets (arbre de travail et historique Git, 300 derniers commits — le dépôt n'en compte que 6)
- [x] Audit des dépendances (`composer audit`)
- [x] Revue de code : tous les fichiers de `src/` (Controller, Database, Menu, Command), `config/services.yaml`, `config/packages/*.yaml`, `public/index.php`, `src/Kernel.php`, les trois fichiers `.env*`, `.gitignore`
- [x] Tests dynamiques : `http://localhost:8000` (serveur de développement `php -S`), en-têtes de sécurité, cookies, CORS, méthodes HTTP, fichiers sensibles exposés, verbosité des erreurs sur des cas limites (identifiants hors bornes, JSON malformé, méthode interdite, routes de profiler)

### 8. Non vérifié / limites

- **Frontend (jalon 2)** : n'existe pas encore (`public/` ne contient que `index.php`). Aucun risque XSS ni manipulation du DOM à auditer pour l'instant ; à refaire quand le jalon 2 sera livré.
- **Générateur de menus (jalon 3) et journal `proposition` (jalon 5)** : pas encore écrits, donc non audités.
- **Permissions du fichier SQLite sur le disque** : la lecture de `var/data/` est bloquée par les permissions de l'environnement d'exécution de cet audit (règle `deny` sur les fichiers `.sqlite`). À vérifier manuellement sur l'hébergement final : le fichier ne doit être lisible que par l'utilisateur du processus PHP.
- **Configuration réelle d'o2switch** : aucune inspection possible de la configuration Apache/PHP-FPM réelle du compte d'hébergement (version PHP exacte du site, `open_basedir`, `expose_php`, présence éventuelle d'un `.htaccess` existant). Le jalon 6 prévoit une vérification manuelle dans cPanel.
- **Test de charge / déni de service réel** : SEC-05 est une lecture de code, pas une mesure sous charge — un audit défensif ne simule pas de déni de service.
- **`gitleaks`** : absent de l'environnement, le scan de secrets s'est appuyé sur la détection par motifs du skill, moins exhaustive qu'un outil dédié.
- **Test d'intrusion humain** : cet audit automatisé et sa revue manuelle ciblée ne remplacent pas un test réalisé par une personne qualifiée, en particulier pour la logique métier (jalons 3 à 5, pas encore écrits) et pour la configuration finale de production.

### 9. Pour aller plus loin

- Avant toute mise en ligne, trancher SEC-01 explicitement (c'est une décision produit, pas une simple correction) et l'inscrire dans `PRD.md` ou `ROADMAP.md` au même titre que les autres décisions actées.
- Ajouter `composer audit` et un scan de secrets (par exemple `gitleaks`) à une intégration continue, si une CI est mise en place un jour pour ce projet.
- Refaire un audit ciblé après le jalon 2 (frontend) pour la partie XSS/DOM, et après les jalons 3 à 5 (générateur, journal) pour la logique métier.
- Avant la mise en production réelle sur o2switch, revalider manuellement la configuration de l'hébergement (permissions de fichiers, `open_basedir`, version PHP réelle du site) — c'est explicitement prévu au jalon 6 et ce rapport ne s'y substitue pas.

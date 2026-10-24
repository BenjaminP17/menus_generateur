# CLAUDE.md

Instructions de travail pour Claude Code dans ce dépôt.

## Contexte

Application web de génération de menus hebdomadaires (déjeuner et dîner) à partir d'une base de plats saisie par l'utilisateur, en tenant compte de la saison et du rythme semaine / week-end. Un seul foyer, sans compte. Le détail des besoins et des règles métier est dans PRD.md (version 0.2, brouillon : les points marqués « À valider » ne sont pas tranchés).

## Stack technique

- Backend : API JSON en PHP 8.1 ou plus récent. Les règles métier (saisons, semaine / week-end, variété) vivent côté PHP.
- Frontend : JavaScript vanilla, modules ES natifs (`type="module"`), sans build ni framework. Un module `api.js` centralise les appels `fetch`. Éviter `innerHTML` avec des données utilisateur.
- Persistance : SQLite via PDO. Le fichier de base est hors du dossier public et n'est jamais commité.
- Hébergement : o2switch (PHP 8.1, `pdo_sqlite` et `sqlite3` disponibles en CLI). Vérifier la version PHP du site web dans cPanel, qui peut différer de la CLI.

## Commandes

À compléter dès que la stack est posée (installation, lancement, tests, lint / format).

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
- Style de code : à définir avec le choix des outils de lint / format.
- Organisation des dossiers : à définir (séparer clairement l'API PHP et le frontend JS).

## Points de vigilance

- Ne pas coder sur une règle marquée « À valider » dans le PRD sans en parler à l'utilisateur ; les questions ouvertes du PRD §7 doivent d'abord être tranchées.
- Hors périmètre V1 : enregistrement des menus (le journal `proposition` des plats proposés, sans menu, est prévu : voir `doc/modelisation-bdd.md`), comptes, nombre de personnes, liste de courses, recettes détaillées, nutrition, allergies, jours de fête. Ne pas les ajouter.
- Ne pas committer de données personnelles ni de base de plats réelle ; pas de secrets dans le dépôt.

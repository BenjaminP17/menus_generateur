# menus_generator

## Description

_À compléter : objectif du projet en une ou deux phrases._

## Prérequis

- PHP 8.2 ou plus, avec les extensions `pdo_sqlite`, `ctype`, `iconv`, `mbstring`
- Composer 2

## Installation

```bash
composer install
```

Le fichier `.env.local` (non versionné) doit contenir un `APP_SECRET` propre à chaque installation :

```bash
echo "APP_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')" > .env.local
```

Créer la base SQLite et son schéma dans `var/data/menus.sqlite` :

```bash
php bin/console app:db:init
```

La commande est idempotente : elle crée ce qui manque et ne supprime jamais rien. Pour repartir d'une base vide en développement, supprimer `var/data/menus.sqlite` avant de la relancer.

## Lancement

```bash
php -S localhost:8000 -t public
```

Vérifier que l'API répond : `http://localhost:8000/api/health` renvoie `{"status":"ok"}`.

## API

| Méthode | Chemin | Description |
|---|---|---|
| GET | `/api/health` | État de l'API et de la base |
| GET | `/api/categories` | Catégories principales |
| GET | `/api/plats` | Liste des plats |
| POST | `/api/plats` | Création d'un plat |
| GET | `/api/plats/{id}` | Lecture d'un plat |
| PUT | `/api/plats/{id}` | Modification d'un plat |
| DELETE | `/api/plats/{id}` | Suppression d'un plat |

Le contrat complet (champs, codes de réponse, format d'erreur) est dans [doc/api.md](doc/api.md).

## Tests

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run
```

## Documentation

- [PRD.md](PRD.md) : besoins produit et règles métier
- [CLAUDE.md](CLAUDE.md) : conventions de travail sur le dépôt
- [ROADMAP.md](ROADMAP.md) : jalons de développement vers la V1
- [doc/modelisation-bdd.md](doc/modelisation-bdd.md) : modèle de données (tables, contraintes, DDL SQLite)
- [doc/api.md](doc/api.md) : contrat de l'API JSON

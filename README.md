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

## Lancement

```bash
php -S localhost:8000 -t public
```

Vérifier que l'API répond : `http://localhost:8000/api/health` renvoie `{"status":"ok"}`.

## Tests

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run
```

## Documentation

- [PRD.md](PRD.md) : besoins produit et règles métier
- [CLAUDE.md](CLAUDE.md) : conventions de travail sur le dépôt
- [doc/modelisation-bdd.md](doc/modelisation-bdd.md) : modèle de données (tables, contraintes, DDL SQLite)

<?php

namespace App\Tests\Support;

use App\Database\SchemaInstaller;

/**
 * Base SQLite en mémoire, avec le schéma de config/schema.sql installé.
 *
 * Les attributs PDO et le PRAGMA reproduisent ceux de App\Database\Database,
 * pour que les tests se comportent comme l'application.
 */
final class InMemoryDatabase
{
    public static function schemaPath(): string
    {
        return dirname(__DIR__, 2).'/config/schema.sql';
    }

    public static function installer(): SchemaInstaller
    {
        return new SchemaInstaller(self::schemaPath());
    }

    /**
     * Connexion vide, sans schéma.
     */
    public static function connection(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /**
     * Connexion avec le schéma installé.
     */
    public static function withSchema(): \PDO
    {
        $pdo = self::connection();
        self::installer()->install($pdo);

        return $pdo;
    }
}

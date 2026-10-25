<?php

namespace App\Database;

/**
 * Connexion SQLite unique, ouverte à la première demande.
 *
 * Active les clés étrangères à chaque connexion : SQLite ne les applique pas par défaut
 * (voir doc/modelisation-bdd.md).
 */
final class Database
{
    private ?\PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function connection(): \PDO
    {
        if (null === $this->pdo) {
            $directory = dirname($this->path);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $pdo = new \PDO('sqlite:'.$this->path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');

            $this->pdo = $pdo;
        }

        return $this->pdo;
    }
}

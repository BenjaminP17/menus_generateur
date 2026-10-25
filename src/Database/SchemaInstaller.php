<?php

namespace App\Database;

/**
 * Installe le schéma de config/schema.sql sur une connexion SQLite.
 *
 * Le fichier est rejouable : l'installation peut être relancée sans rien détruire.
 * La connexion est passée en paramètre, pour permettre l'installation sur une base
 * en mémoire dans les tests.
 */
final class SchemaInstaller
{
    public function __construct(private readonly string $schemaPath)
    {
    }

    public function install(\PDO $pdo): void
    {
        $sql = @file_get_contents($this->schemaPath);
        if (false === $sql) {
            throw new \RuntimeException(sprintf('Schéma illisible : %s', $this->schemaPath));
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();

            throw $error;
        }
    }
}

<?php

namespace App\Database;

use App\Menu\Category;

/**
 * Lecture des catégories principales (table de référence, voir doc/modelisation-bdd.md).
 */
final class CategoryRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return list<Category> dans l'ordre d'insertion du PRD §5.1 (pâtes, …, autre)
     */
    public function findAll(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT id, libelle FROM categorie_principale ORDER BY id')->fetchAll();

        return array_map(
            static fn (array $row): Category => new Category((int) $row['id'], (string) $row['libelle']),
            $rows,
        );
    }

    public function exists(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM categorie_principale WHERE id = :id');
        $statement->execute(['id' => $id]);

        return false !== $statement->fetchColumn();
    }
}

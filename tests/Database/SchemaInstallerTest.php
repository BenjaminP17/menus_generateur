<?php

namespace App\Tests\Database;

use App\Database\SchemaInstaller;
use App\Tests\Support\InMemoryDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaInstaller::class)]
final class SchemaInstallerTest extends TestCase
{
    public function testCreatesTablesAndIndexes(): void
    {
        $pdo = InMemoryDatabase::withSchema();

        $this->assertSame(
            ['categorie_principale', 'plat', 'proposition'],
            $this->names($pdo, 'table'),
        );
        $this->assertSame(
            ['idx_proposition_date', 'idx_proposition_plat_date'],
            $this->names($pdo, 'index'),
        );
    }

    public function testInsertsTheEightReferenceCategories(): void
    {
        $pdo = InMemoryDatabase::withSchema();

        $libelles = $pdo->query('SELECT libelle FROM categorie_principale ORDER BY id')
            ->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(
            ['pâtes', 'riz / céréales', 'viande', 'poisson', 'œufs', 'légumes', 'soupe', 'autre'],
            $libelles,
        );
    }

    public function testSecondInstallChangesNothing(): void
    {
        $pdo = InMemoryDatabase::withSchema();
        $this->insertDish($pdo, ['nom' => 'Quiche', 'nom_normalise' => 'quiche']);

        InMemoryDatabase::installer()->install($pdo);

        $this->assertSame(8, $this->countRows($pdo, 'categorie_principale'));
        $this->assertSame(1, $this->countRows($pdo, 'plat'));
    }

    public function testFailsOnMissingSchemaFile(): void
    {
        $installer = new SchemaInstaller(sys_get_temp_dir().'/schema-absent-'.uniqid().'.sql');

        $this->expectException(\RuntimeException::class);

        $installer->install(InMemoryDatabase::connection());
    }

    public function testRejectsDuplicateNormalisedName(): void
    {
        $pdo = InMemoryDatabase::withSchema();
        $this->insertDish($pdo, ['nom' => 'Quiche', 'nom_normalise' => 'quiche']);

        $this->expectException(\PDOException::class);

        $this->insertDish($pdo, ['nom' => 'Quîche', 'nom_normalise' => 'quiche']);
    }

    /**
     * @return array<string, list<array<string, int|string>>>
     */
    public static function invalidDishProvider(): array
    {
        return [
            'saison hors liste' => [['saison' => 'ete']],
            'type hors liste' => [['type' => 'vegan']],
            'niveau hors liste' => [['niveau' => 'moyen']],
            'poids trop élevé' => [['poids' => 4]],
            'poids trop faible' => [['poids' => 0]],
            'repas non booléen' => [['convient_dejeuner' => 2]],
            'aucun repas autorisé' => [['convient_dejeuner' => 0, 'convient_diner' => 0]],
            'catégorie inconnue' => [['categorie_principale_id' => 999]],
        ];
    }

    /**
     * @param array<string, int|string> $overrides
     */
    #[DataProvider('invalidDishProvider')]
    public function testRejectsInvalidDish(array $overrides): void
    {
        $pdo = InMemoryDatabase::withSchema();

        $this->expectException(\PDOException::class);

        $this->insertDish($pdo, $overrides);
    }

    /**
     * @return array<string, list<array<string, int|string>>>
     */
    public static function invalidPropositionProvider(): array
    {
        return [
            'jour hors semaine' => [['jour' => 8]],
            'jour nul' => [['jour' => 0]],
            'repas hors liste' => [['repas' => 'gouter']],
            'origine hors liste' => [['origine' => 'hasard']],
            'plat inconnu' => [['plat_id' => 999]],
        ];
    }

    /**
     * @param array<string, int|string> $overrides
     */
    #[DataProvider('invalidPropositionProvider')]
    public function testRejectsInvalidProposition(array $overrides): void
    {
        $pdo = InMemoryDatabase::withSchema();
        $platId = $this->insertDish($pdo);

        $this->expectException(\PDOException::class);

        $this->insertProposition($pdo, $platId, $overrides);
    }

    public function testDeletingADishDeletesItsPropositions(): void
    {
        $pdo = InMemoryDatabase::withSchema();
        $platId = $this->insertDish($pdo);
        $this->insertProposition($pdo, $platId);

        $pdo->exec('DELETE FROM plat WHERE id = '.$platId);

        $this->assertSame(0, $this->countRows($pdo, 'proposition'));
    }

    /**
     * @param array<string, int|string> $overrides
     *
     * @return int identifiant du plat créé
     */
    private function insertDish(\PDO $pdo, array $overrides = []): int
    {
        $values = array_merge([
            'nom' => 'Gratin de courgettes',
            'nom_normalise' => 'gratin de courgettes',
            'convient_dejeuner' => 1,
            'convient_diner' => 1,
            'saison' => 'printemps_ete',
            'type' => 'vegetarien',
            'niveau' => 'rapide',
            'categorie_principale_id' => 1,
            'poids' => 2,
            'cree_le' => '2026-10-25T12:00:00+00:00',
            'modifie_le' => '2026-10-25T12:00:00+00:00',
        ], $overrides);

        $this->insert($pdo, 'plat', $values);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, int|string> $overrides
     */
    private function insertProposition(\PDO $pdo, int $platId, array $overrides = []): void
    {
        $this->insert($pdo, 'proposition', array_merge([
            'plat_id' => $platId,
            'date_menu' => '2026-10-19',
            'jour' => 1,
            'repas' => 'dejeuner',
            'origine' => 'suggestion',
            'cree_le' => '2026-10-25T12:00:00+00:00',
        ], $overrides));
    }

    /**
     * @param array<string, int|string> $values
     */
    private function insert(\PDO $pdo, string $table, array $values): void
    {
        $columns = array_keys($values);
        $statement = $pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $column): string => ':'.$column, $columns)),
        ));

        $statement->execute($values);
    }

    /**
     * @return list<string>
     */
    private function names(\PDO $pdo, string $type): array
    {
        $statement = $pdo->prepare(
            "SELECT name FROM sqlite_master WHERE type = :type AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );
        $statement->execute(['type' => $type]);

        /** @var list<string> $names */
        $names = $statement->fetchAll(\PDO::FETCH_COLUMN);

        return $names;
    }

    private function countRows(\PDO $pdo, string $table): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    }
}

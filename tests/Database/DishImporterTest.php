<?php

namespace App\Tests\Database;

use App\Database\CategoryRepository;
use App\Database\DishImporter;
use App\Database\DishImportReport;
use App\Menu\DishInputValidator;
use App\Menu\DishNameNormalizer;
use App\Tests\Support\DishFactory;
use App\Tests\Support\InMemoryDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DishImporter::class)]
#[CoversClass(DishImportReport::class)]
final class DishImporterTest extends TestCase
{
    private \PDO $pdo;

    private DishImporter $importer;

    protected function setUp(): void
    {
        $this->pdo = InMemoryDatabase::withSchema();
        $normalizer = new DishNameNormalizer();
        $this->importer = new DishImporter(
            $this->pdo,
            DishFactory::repository($this->pdo),
            new CategoryRepository($this->pdo),
            new DishInputValidator($normalizer),
            $normalizer,
        );
    }

    public function testImportsTheDishesAndIgnoresDocumentationKeys(): void
    {
        $report = $this->importer->import([
            '_aide' => 'documentation',
            'plats' => [
                $this->dish('Quiche lorraine', ['categorie' => 'œufs', 'poids' => 3]),
                $this->dish('Gratin', ['categorie' => 'Légumes', 'convient_diner' => false]),
            ],
        ]);

        $this->assertTrue($report->isSuccessful());
        $this->assertSame(['Quiche lorraine', 'Gratin'], $report->imported);
        $dishes = DishFactory::repository($this->pdo)->findAll();
        $this->assertCount(2, $dishes);
        $gratin = DishFactory::repository($this->pdo)->findByName('gratin');
        $this->assertNotNull($gratin);
        $this->assertFalse($gratin->fitsDinner);
        $this->assertTrue($gratin->fitsLunch);
        $quiche = DishFactory::repository($this->pdo)->findByName('Quiche lorraine');
        $this->assertSame(3, $quiche?->weight);
    }

    public function testMatchesCategoriesIgnoringCaseAndAccents(): void
    {
        $report = $this->importer->import(['plats' => [
            $this->dish('A', ['categorie' => 'PÂTES']),
            $this->dish('B', ['categorie' => 'riz / céréales']),
            $this->dish('C', ['categorie' => 'oeufs']),
        ]]);

        $this->assertTrue($report->isSuccessful());
        $this->assertCount(3, $report->imported);
    }

    public function testSkipsADishAlreadyInTheBaseWithoutChangingIt(): void
    {
        $repository = DishFactory::repository($this->pdo);
        $repository->create(DishFactory::rawInput('Quiche lorraine', weight: 1));

        $report = $this->importer->import(['plats' => [
            $this->dish('Quîche Lorraine', ['poids' => 3]),
            $this->dish('Nouveau plat'),
        ]]);

        $this->assertTrue($report->isSuccessful());
        $this->assertSame(['Quîche Lorraine'], $report->skipped);
        $this->assertSame(['Nouveau plat'], $report->imported);
        $this->assertSame(1, $repository->findByName('Quiche lorraine')?->weight);
    }

    public function testASingleInvalidDishWritesNothing(): void
    {
        $report = $this->importer->import(['plats' => [
            $this->dish('Valide'),
            $this->dish('Sans repas', ['convient_dejeuner' => false, 'convient_diner' => false]),
            $this->dish('Catégorie bizarre', ['categorie' => 'dessert']),
            $this->dish('Valide'),
            'pas un objet',
        ]]);

        $this->assertFalse($report->isSuccessful());
        $this->assertSame([], $report->imported);
        $this->assertCount(4, $report->errors);
        $this->assertStringContainsString('Plat n° 2 (« Sans repas »)', $report->errors[0]);
        $this->assertStringContainsString('catégorie inconnue', $report->errors[1]);
        $this->assertStringContainsString('plusieurs fois', $report->errors[2]);
        $this->assertSame([], DishFactory::repository($this->pdo)->findAll());
    }

    public function testDryRunValidatesWithoutWriting(): void
    {
        $report = $this->importer->import(['plats' => [$this->dish('A'), $this->dish('B')]], dryRun: true);

        $this->assertSame(['A', 'B'], $report->imported);
        $this->assertSame([], DishFactory::repository($this->pdo)->findAll());
    }

    public function testRequiresAListOfDishes(): void
    {
        foreach ([[], ['plats' => 'x'], ['plats' => ['a' => []]]] as $data) {
            $this->assertFalse($this->importer->import($data)->isSuccessful());
        }
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function dish(string $name, array $overrides = []): array
    {
        return $overrides + [
            'nom' => $name,
            'saison' => 'hybride',
            'type' => 'classique',
            'niveau' => 'rapide',
            'categorie' => 'viande',
        ];
    }
}

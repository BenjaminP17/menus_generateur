<?php

namespace App\Tests\Database;

use App\Database\PropositionRepository;
use App\Menu\MealKind;
use App\Menu\MenuPlacement;
use App\Menu\ProposalOrigin;
use App\Tests\Support\DishFactory;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropositionRepository::class)]
final class PropositionRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private FixedClock $clock;
    private PropositionRepository $repository;

    /** @var list<int> */
    private array $dishIds = [];

    protected function setUp(): void
    {
        $this->pdo = InMemoryDatabase::withSchema();
        // Jeudi 29 octobre 2026 : la semaine en cours commence le lundi 26.
        $this->clock = new FixedClock('2026-10-29T12:00:00+00:00');
        $this->repository = new PropositionRepository($this->pdo, $this->clock);

        $dishes = DishFactory::repository($this->pdo, $this->clock);
        foreach (['Plat A', 'Plat B', 'Plat C'] as $name) {
            $this->dishIds[] = $dishes->create(DishFactory::rawInput($name))->id;
        }
    }

    public function testStoresOneRowPerMeal(): void
    {
        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-26'), [
            new MenuPlacement(0, MealKind::Lunch, $this->dishIds[0]),
            new MenuPlacement(6, MealKind::Dinner, $this->dishIds[1], ProposalOrigin::FreeChoice),
        ]);

        $this->assertSame([
            ['plat_id' => $this->dishIds[0], 'date_menu' => '2026-10-26', 'jour' => 1, 'repas' => 'dejeuner', 'origine' => 'suggestion', 'cree_le' => '2026-10-29T12:00:00+00:00'],
            ['plat_id' => $this->dishIds[1], 'date_menu' => '2026-10-26', 'jour' => 7, 'repas' => 'diner', 'origine' => 'choix_libre', 'cree_le' => '2026-10-29T12:00:00+00:00'],
        ], $this->rows());
    }

    public function testANewValidationReplacesTheRowsOfThatWeekOnly(): void
    {
        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-19'), [new MenuPlacement(0, MealKind::Lunch, $this->dishIds[2])]);
        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-26'), [
            new MenuPlacement(0, MealKind::Lunch, $this->dishIds[0]),
            new MenuPlacement(1, MealKind::Lunch, $this->dishIds[1]),
        ]);

        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-26'), [new MenuPlacement(2, MealKind::Dinner, $this->dishIds[1])]);

        $this->assertSame(
            [['2026-10-19', $this->dishIds[2]], ['2026-10-26', $this->dishIds[1]]],
            array_map(static fn (array $row): array => [$row['date_menu'], $row['plat_id']], $this->rows()),
        );
    }

    public function testAFailedValidationLeavesTheWeekUntouched(): void
    {
        $monday = new \DateTimeImmutable('2026-10-26');
        $this->repository->replaceWeek($monday, [new MenuPlacement(0, MealKind::Lunch, $this->dishIds[0])]);

        try {
            $this->repository->replaceWeek($monday, [
                new MenuPlacement(0, MealKind::Lunch, $this->dishIds[1]),
                new MenuPlacement(1, MealKind::Lunch, 999999), // plat inconnu : clé étrangère
            ]);
            $this->fail('Une clé étrangère violée aurait dû lever une exception.');
        } catch (\PDOException) {
            // attendu
        }

        $this->assertSame([$this->dishIds[0]], array_column($this->rows(), 'plat_id'));
    }

    public function testFindsTheLastWeekOfEachDishExceptTheGivenOne(): void
    {
        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-12'), [
            new MenuPlacement(0, MealKind::Lunch, $this->dishIds[0]),
            new MenuPlacement(1, MealKind::Lunch, $this->dishIds[1]),
        ]);
        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-19'), [new MenuPlacement(0, MealKind::Lunch, $this->dishIds[0])]);
        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-26'), [
            new MenuPlacement(0, MealKind::Lunch, $this->dishIds[2]),
            new MenuPlacement(1, MealKind::Lunch, $this->dishIds[1]),
        ]);

        $last = $this->repository->lastProposedByDish(new \DateTimeImmutable('2026-10-26'));

        $this->assertSame(
            [$this->dishIds[0] => '2026-10-19', $this->dishIds[1] => '2026-10-12'],
            array_map(static fn (\DateTimeImmutable $date): string => $date->format('Y-m-d'), $last),
        );
    }

    public function testPurgesRowsOlderThanEightWeeks(): void
    {
        // Limite : lundi 26 octobre moins 8 semaines, soit le 31 août 2026 (conservé).
        foreach (['2026-08-24', '2026-08-31', '2026-10-26'] as $monday) {
            $this->insert($monday);
        }

        $this->assertSame(1, $this->repository->purge());

        $this->assertSame(['2026-08-31', '2026-10-26'], array_column($this->rows(), 'date_menu'));
    }

    public function testValidatingAlsoPurges(): void
    {
        $this->insert('2026-08-03');

        $this->repository->replaceWeek(new \DateTimeImmutable('2026-10-26'), [new MenuPlacement(0, MealKind::Lunch, $this->dishIds[0])]);

        $this->assertSame(['2026-10-26'], array_column($this->rows(), 'date_menu'));
    }

    public function testClearEmptiesTheJournalButKeepsTheDishes(): void
    {
        $this->insert('2026-10-19');
        $this->insert('2026-10-26');

        $this->assertSame(2, $this->repository->clear());

        $this->assertSame([], $this->rows());
        $this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM plat')->fetchColumn());
        $this->assertSame(8, (int) $this->pdo->query('SELECT COUNT(*) FROM categorie_principale')->fetchColumn());
    }

    public function testDeletingADishErasesItsHistory(): void
    {
        $this->insert('2026-10-26');

        $this->pdo->exec('DELETE FROM plat WHERE id = '.$this->dishIds[0]);

        $this->assertSame([], $this->rows());
    }

    private function insert(string $monday): void
    {
        $this->pdo->prepare(
            "INSERT INTO proposition (plat_id, date_menu, jour, repas, origine, cree_le) VALUES (:plat_id, :date_menu, 1, 'dejeuner', 'suggestion', '2026-10-29T12:00:00+00:00')",
        )->execute(['plat_id' => $this->dishIds[0], 'date_menu' => $monday]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT plat_id, date_menu, jour, repas, origine, cree_le FROM proposition ORDER BY date_menu, jour, repas')->fetchAll();

        return $rows;
    }
}

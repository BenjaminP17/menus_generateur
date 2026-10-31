<?php

namespace App\Tests\Database;

use App\Database\DishRepository;
use App\Database\DuplicateDishNameException;
use App\Menu\Dish;
use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\Season;
use App\Tests\Support\DishFactory;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DishRepository::class)]
#[CoversClass(DuplicateDishNameException::class)]
final class DishRepositoryTest extends TestCase
{
    public function testFindAllIsEmptyOnAFreshBase(): void
    {
        $this->assertSame([], $this->repository()->findAll());
    }

    public function testFindAllSortsIgnoringAccentsAndCase(): void
    {
        $repository = $this->repository();
        foreach (['Épinards à la crème', 'avocat farci', 'Œufs au plat', 'Zucchini'] as $name) {
            $repository->create(DishFactory::rawInput($name));
        }

        $this->assertSame(
            ['avocat farci', 'Épinards à la crème', 'Œufs au plat', 'Zucchini'],
            array_map(static fn (Dish $dish): string => $dish->name, $repository->findAll()),
        );
    }

    public function testCreateReturnsAStoredDish(): void
    {
        $dish = $this->repository()->create(DishFactory::input(['nom' => '  Quiche   lorraine ']));

        $this->assertGreaterThan(0, $dish->id);
        $this->assertSame('Quiche lorraine', $dish->name);
        $this->assertSame('quiche lorraine', $dish->normalizedName);
        $this->assertSame('2026-10-25T12:00:00+00:00', $dish->createdAt->format(\DateTimeInterface::ATOM));
        $this->assertEquals($dish->createdAt, $dish->updatedAt);
    }

    /**
     * Un false lié par PDO deviendrait une chaîne vide, que le CHECK de la colonne refuse.
     */
    public function testStoresAndReadsBackMealFlags(): void
    {
        $repository = $this->repository();

        $lunchOnly = $repository->create(DishFactory::rawInput('Salade', fitsDinner: false));
        $dinnerOnly = $repository->create(DishFactory::rawInput('Soupe', fitsLunch: false));

        $this->assertTrue($lunchOnly->fitsLunch);
        $this->assertFalse($lunchOnly->fitsDinner);
        $this->assertFalse($dinnerOnly->fitsLunch);
        $this->assertTrue($dinnerOnly->fitsDinner);
    }

    /**
     * @return iterable<string, array{Season, DishType, Level, int}>
     */
    public static function attributeProvider(): iterable
    {
        foreach (Season::cases() as $season) {
            yield 'saison '.$season->value => [$season, DishType::Classic, Level::Quick, 2];
        }

        foreach (DishType::cases() as $type) {
            yield 'type '.$type->value => [Season::Hybrid, $type, Level::Quick, 2];
        }

        foreach (Level::cases() as $level) {
            yield 'niveau '.$level->value => [Season::Hybrid, DishType::Classic, $level, 2];
        }

        foreach ([1, 2, 3] as $weight) {
            yield 'poids '.$weight => [Season::Hybrid, DishType::Classic, Level::Quick, $weight];
        }
    }

    #[DataProvider('attributeProvider')]
    public function testEveryEnumValueIsAcceptedBySchema(
        Season $season,
        DishType $type,
        Level $level,
        int $weight,
    ): void {
        $dish = $this->repository()->create(
            DishFactory::rawInput('Plat test', $season, $type, $level, weight: $weight),
        );

        $this->assertSame($season, $dish->season);
        $this->assertSame($type, $dish->type);
        $this->assertSame($level, $dish->level);
        $this->assertSame($weight, $dish->weight);
    }

    public function testFindReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->repository()->find(999));
    }

    public function testUpdateAdvancesOnlyTheModificationDate(): void
    {
        $clock = new FixedClock();
        $repository = $this->repository($clock);
        $created = $repository->create(DishFactory::input());

        $clock->advance('+1 day');
        $updated = $repository->update($created->id, DishFactory::input([
            'nom' => 'Gratin de courgettes au curry',
            'niveau' => 'elabore',
        ]));

        $this->assertNotNull($updated);
        $this->assertSame('Gratin de courgettes au curry', $updated->name);
        $this->assertSame('gratin de courgettes au curry', $updated->normalizedName);
        $this->assertSame(Level::Elaborate, $updated->level);
        $this->assertEquals($created->createdAt, $updated->createdAt);
        $this->assertSame('2026-10-26T12:00:00+00:00', $updated->updatedAt->format(\DateTimeInterface::ATOM));
    }

    public function testUpdateReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->repository()->update(999, DishFactory::input()));
    }

    public function testUpdateKeepingItsOwnNameIsAllowed(): void
    {
        $repository = $this->repository();
        $created = $repository->create(DishFactory::input());

        $updated = $repository->update($created->id, DishFactory::input(['poids' => 3]));

        $this->assertNotNull($updated);
        $this->assertSame(3, $updated->weight);
    }

    public function testCreateRejectsADuplicateName(): void
    {
        $repository = $this->repository();
        $repository->create(DishFactory::input(['nom' => 'Quiche']));

        $this->expectException(DuplicateDishNameException::class);

        $repository->create(DishFactory::input(['nom' => 'Quiche']));
    }

    public function testCreateRejectsANameDifferingOnlyByAccentsOrCase(): void
    {
        $repository = $this->repository();
        $existing = $repository->create(DishFactory::input(['nom' => 'Quiche']));

        try {
            $repository->create(DishFactory::input(['nom' => 'QUÎCHE']));
            $this->fail('Un doublon était attendu.');
        } catch (DuplicateDishNameException $error) {
            $this->assertSame($existing->id, $error->existing()->id);
        }
    }

    public function testUpdateRejectsTheNameOfAnotherDish(): void
    {
        $repository = $this->repository();
        $repository->create(DishFactory::input(['nom' => 'Quiche']));
        $other = $repository->create(DishFactory::input(['nom' => 'Soupe de potiron']));

        $this->expectException(DuplicateDishNameException::class);

        $repository->update($other->id, DishFactory::input(['nom' => 'Quiche']));
    }

    public function testFindByNameIgnoresAccentsAndCase(): void
    {
        $repository = $this->repository();
        $created = $repository->create(DishFactory::input(['nom' => 'Œufs à la coque']));

        $found = $repository->findByName('oeufs A LA coque');

        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
        $this->assertNull($repository->findByName('Plat absent'));
    }

    public function testDeleteReportsWhetherADishWasRemoved(): void
    {
        $repository = $this->repository();
        $created = $repository->create(DishFactory::input());

        $this->assertTrue($repository->delete($created->id));
        $this->assertNull($repository->find($created->id));
        $this->assertFalse($repository->delete($created->id));
    }

    /**
     * Suppression libre du PRD §5.1 : l'historique du plat part en cascade.
     */
    public function testDeleteAlsoRemovesTheDishHistory(): void
    {
        $pdo = InMemoryDatabase::withSchema();
        $repository = DishFactory::repository($pdo);
        $created = $repository->create(DishFactory::input());
        $pdo->prepare(
            'INSERT INTO proposition (plat_id, date_menu, jour, repas, origine, cree_le)
             VALUES (:plat_id, :date_menu, 1, :repas, :origine, :cree_le)'
        )->execute([
            'plat_id' => $created->id,
            'date_menu' => '2026-10-19',
            'repas' => 'dejeuner',
            'origine' => 'suggestion',
            'cree_le' => '2026-10-25T12:00:00+00:00',
        ]);

        $repository->delete($created->id);

        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM proposition')->fetchColumn());
    }

    /**
     * Une catégorie inexistante viole la clé étrangère : l'erreur ne doit pas être confondue
     * avec un doublon de nom, les deux partageant le même SQLSTATE.
     */
    public function testCreateWithAnUnknownCategoryFailsAsAForeignKeyViolation(): void
    {
        $this->expectException(\PDOException::class);

        $this->repository()->create(DishFactory::rawInput('Plat sans catégorie', categoryId: 999));
    }

    private function repository(?FixedClock $clock = null): DishRepository
    {
        return DishFactory::repository(InMemoryDatabase::withSchema(), $clock);
    }
}

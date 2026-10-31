<?php

namespace App\Tests\Menu;

use App\Menu\BaseDiagnostic;
use App\Menu\BaseDiagnosticReport;
use App\Menu\Level;
use App\Menu\MealDiagnostic;
use App\Menu\MealKind;
use App\Menu\Season;
use App\Menu\SeasonResolver;
use App\Tests\Support\DishFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BaseDiagnostic::class)]
#[CoversClass(BaseDiagnosticReport::class)]
#[CoversClass(MealDiagnostic::class)]
final class BaseDiagnosticTest extends TestCase
{
    public function testAFullBaseIsSufficient(): void
    {
        $report = $this->diagnose(DishFactory::catalogue(), '2026-12-02');

        $this->assertTrue($report->sufficient);
        $this->assertSame(Season::AutumnWinter, $report->season);
        foreach ($report->meals as $meal) {
            $this->assertTrue($meal->sufficient);
            $this->assertTrue($meal->levelsCovered);
        }
    }

    public function testAnEmptyBaseIsInsufficient(): void
    {
        $report = $this->diagnose([], '2026-12-02');

        $this->assertFalse($report->sufficient);
        $this->assertSame(0, $report->distinctDishes);
        $this->assertSame([MealKind::Lunch, MealKind::Dinner], array_map(static fn (MealDiagnostic $m): MealKind => $m->kind, $report->meals));
    }

    public function testDishesOfTheOppositeSeasonDoNotCount(): void
    {
        $dishes = [];
        for ($id = 1; $id <= 20; ++$id) {
            $dishes[] = DishFactory::dish($id, Season::SpringSummer);
        }

        $this->assertTrue($this->diagnose($dishes, '2026-07-14')->sufficient);
        $this->assertFalse($this->diagnose($dishes, '2026-12-02')->sufficient);
    }

    public function testADishForBothMealsCountsOnceInTheTotal(): void
    {
        // Sept plats pour les deux repas : chaque repas en a sept, mais il n'y a que sept plats distincts.
        $dishes = [];
        for ($id = 1; $id <= 7; ++$id) {
            $dishes[] = DishFactory::dish($id, Season::Hybrid);
        }

        $report = $this->diagnose($dishes, '2026-07-14');

        $this->assertSame(7, $report->meals[0]->available);
        $this->assertSame(7, $report->meals[1]->available);
        $this->assertTrue($report->meals[0]->sufficient);
        $this->assertSame(7, $report->distinctDishes);
        $this->assertFalse($report->sufficient);
    }

    public function testOneMealCanBeShortWhileTheOtherIsEnough(): void
    {
        $dishes = [];
        for ($id = 1; $id <= 14; ++$id) {
            $dishes[] = DishFactory::dish($id, Season::Hybrid, fitsLunch: $id <= 3, fitsDinner: true);
        }

        $report = $this->diagnose($dishes, '2026-07-14');

        $this->assertFalse($report->meals[0]->sufficient, 'trois déjeuners seulement');
        $this->assertTrue($report->meals[1]->sufficient);
        $this->assertFalse($report->sufficient);
    }

    public function testLevelsAreReportedApartFromSufficiency(): void
    {
        $dishes = [];
        for ($id = 1; $id <= 20; ++$id) {
            $dishes[] = DishFactory::dish($id, Season::Hybrid, level: Level::Quick);
        }

        $report = $this->diagnose($dishes, '2026-07-14');

        $this->assertTrue($report->sufficient);
        $this->assertFalse($report->meals[0]->levelsCovered);
        $this->assertSame(20, $report->meals[0]->availableQuick);
        $this->assertSame(0, $report->meals[0]->availableElaborate);
    }

    /**
     * @param list<\App\Menu\Dish> $dishes
     */
    private function diagnose(array $dishes, string $date): BaseDiagnosticReport
    {
        return (new BaseDiagnostic(new SeasonResolver()))->diagnose($dishes, new \DateTimeImmutable($date));
    }
}

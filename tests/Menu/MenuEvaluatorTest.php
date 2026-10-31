<?php

namespace App\Tests\Menu;

use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\MealKind;
use App\Menu\MenuEvaluator;
use App\Menu\MenuMeal;
use App\Menu\MenuRequest;
use App\Menu\MenuWarning;
use App\Menu\Season;
use App\Menu\SeasonResolver;
use App\Menu\VegetarianShare;
use App\Menu\WarningKind;
use App\Tests\Support\DishFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuEvaluator::class)]
#[CoversClass(MenuWarning::class)]
final class MenuEvaluatorTest extends TestCase
{
    public function testAMenuThatRespectsEveryRuleHasNoWarning(): void
    {
        $meals = [
            new MenuMeal(0, MealKind::Lunch, DishFactory::dish(1)),
            new MenuMeal(5, MealKind::Dinner, DishFactory::dish(2, level: Level::Elaborate)),
        ];

        $this->assertSame([], $this->evaluate($meals, [0], [5]));
    }

    public function testReportsEachMissingMeal(): void
    {
        $warnings = $this->evaluate([], [0, 1], [2]);

        $this->assertSame(
            [[WarningKind::MissingDish, 0, MealKind::Lunch], [WarningKind::MissingDish, 1, MealKind::Lunch], [WarningKind::MissingDish, 2, MealKind::Dinner]],
            array_map(static fn (MenuWarning $w): array => [$w->kind, $w->day, $w->meal], $warnings),
        );
    }

    public function testReportsADishUsedTwiceOnTheSecondMeal(): void
    {
        $dish = DishFactory::dish(1);
        $warnings = $this->evaluate([
            new MenuMeal(0, MealKind::Lunch, $dish),
            new MenuMeal(1, MealKind::Lunch, $dish),
        ], [0, 1], [3]);

        $duplicates = $this->ofKind($warnings, WarningKind::DuplicateDish);
        $this->assertCount(1, $duplicates);
        $this->assertSame(1, $duplicates[0]->day);
    }

    public function testReportsADishThatDoesNotFitTheMeal(): void
    {
        $warnings = $this->evaluate([
            new MenuMeal(0, MealKind::Dinner, DishFactory::dish(1, fitsDinner: false)),
        ], [3], [0]);

        $this->assertCount(1, $this->ofKind($warnings, WarningKind::MealNotAllowed));
    }

    public function testReportsADishOfTheOppositeSeasonButNotAHybrid(): void
    {
        $warnings = $this->evaluate([
            new MenuMeal(0, MealKind::Lunch, DishFactory::dish(1, Season::AutumnWinter)),
            new MenuMeal(1, MealKind::Lunch, DishFactory::dish(2, Season::Hybrid)),
            new MenuMeal(2, MealKind::Lunch, DishFactory::dish(3, Season::SpringSummer)),
        ], [0, 1, 2], [3], '2026-07-14');

        $outOfSeason = $this->ofKind($warnings, WarningKind::OutOfSeason);
        $this->assertCount(1, $outOfSeason);
        $this->assertSame(0, $outOfSeason[0]->day);
    }

    public function testReportsTheLevelOfTheDayNotRespected(): void
    {
        $warnings = $this->evaluate([
            new MenuMeal(0, MealKind::Lunch, DishFactory::dish(1, level: Level::Elaborate)),
            new MenuMeal(6, MealKind::Lunch, DishFactory::dish(2, level: Level::Quick)),
        ], [0, 6], [3]);

        $this->assertSame([0, 6], array_map(static fn (MenuWarning $w): ?int => $w->day, $this->ofKind($warnings, WarningKind::LevelFallback)));
    }

    public function testReportsTooFewAndTooManyVegetarianMeals(): void
    {
        $classic = [
            new MenuMeal(0, MealKind::Lunch, DishFactory::dish(1)),
            new MenuMeal(1, MealKind::Lunch, DishFactory::dish(2)),
            new MenuMeal(2, MealKind::Lunch, DishFactory::dish(3)),
        ];
        $vegetarian = [
            new MenuMeal(0, MealKind::Lunch, DishFactory::dish(1, type: DishType::Vegetarian)),
            new MenuMeal(1, MealKind::Lunch, DishFactory::dish(2, type: DishType::Vegetarian)),
            new MenuMeal(2, MealKind::Lunch, DishFactory::dish(3)),
        ];

        $this->assertCount(1, $this->ofKind($this->evaluate($classic, [0, 1, 2], [], share: VegetarianShare::Mostly), WarningKind::VegetarianShortfall));
        $this->assertCount(1, $this->ofKind($this->evaluate($vegetarian, [0, 1, 2], [], share: VegetarianShare::Some), WarningKind::VegetarianExcess));
        $this->assertCount(1, $this->ofKind($this->evaluate($vegetarian, [0, 1, 2], [], share: VegetarianShare::None), WarningKind::VegetarianExcess));
        $this->assertSame([], $this->ofKind($this->evaluate($vegetarian, [0, 1, 2], [], share: VegetarianShare::Mostly), WarningKind::VegetarianExcess));
    }

    /**
     * @param list<MenuMeal> $meals
     * @param list<int>      $lunchDays
     * @param list<int>      $dinnerDays
     *
     * @return list<MenuWarning>
     */
    private function evaluate(array $meals, array $lunchDays, array $dinnerDays, string $date = '2026-07-14', VegetarianShare $share = VegetarianShare::None): array
    {
        $request = new MenuRequest($lunchDays, $dinnerDays, $share, new \DateTimeImmutable($date));

        return (new MenuEvaluator(new SeasonResolver()))->evaluate($meals, $request);
    }

    /**
     * @param list<MenuWarning> $warnings
     *
     * @return list<MenuWarning>
     */
    private function ofKind(array $warnings, WarningKind $kind): array
    {
        return array_values(array_filter($warnings, static fn (MenuWarning $w): bool => $kind === $w->kind));
    }
}

<?php

namespace App\Tests\Menu;

use App\Menu\Dish;
use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\MealKind;
use App\Menu\Menu;
use App\Menu\MenuEvaluator;
use App\Menu\MenuGenerator;
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
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(MenuGenerator::class)]
#[CoversClass(Menu::class)]
#[CoversClass(MenuMeal::class)]
#[CoversClass(MenuWarning::class)]
#[CoversClass(MenuRequest::class)]
final class MenuGeneratorTest extends TestCase
{
    private const SUMMER = '2026-07-14';
    private const WINTER = '2026-12-02';
    private const ALL = [0, 1, 2, 3, 4, 5, 6];

    public function testContainsExactlyTheMealsOfTheChosenDays(): void
    {
        $dishes = DishFactory::catalogue();
        $choices = [
            'un jour' => [3],
            'semaine' => [0, 1, 2, 3, 4],
            'week-end' => [5, 6],
            'discontinu' => [0, 2, 6],
            'tous' => self::ALL,
        ];

        foreach ($choices as $lunchDays) {
            foreach ($choices as $dinnerDays) {
                foreach ([1, 2, 3] as $seed) {
                    $menu = $this->generate($dishes, $lunchDays, $dinnerDays, VegetarianShare::Some, self::WINTER, $seed);

                    $this->assertSame($lunchDays, $this->daysOf($menu, MealKind::Lunch));
                    $this->assertSame($dinnerDays, $this->daysOf($menu, MealKind::Dinner));
                    $this->assertTrue($menu->isComplete());
                }
            }
        }
    }

    public function testNeverRepeatsADish(): void
    {
        for ($seed = 1; $seed <= 50; ++$seed) {
            $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::Mostly, self::SUMMER, $seed);

            $ids = array_map(static fn (MenuMeal $meal): int => $meal->dish->id, $menu->meals);
            $this->assertSame($ids, array_values(array_unique($ids)));
        }
    }

    public function testRespectsTheMealAndExcludesTheOppositeSeason(): void
    {
        foreach ([[self::SUMMER, Season::AutumnWinter], [self::WINTER, Season::SpringSummer]] as [$date, $excluded]) {
            for ($seed = 1; $seed <= 30; ++$seed) {
                $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::Some, $date, $seed);

                foreach ($menu->meals as $meal) {
                    $this->assertTrue($meal->dish->fits($meal->kind));
                    $this->assertNotSame($excluded, $meal->dish->season);
                }
            }
        }
    }

    public function testPlansWeekdaysQuickAndWeekendElaborateWhenTheBaseAllowsIt(): void
    {
        for ($seed = 1; $seed <= 30; ++$seed) {
            $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::Some, self::WINTER, $seed);

            foreach ($menu->meals as $meal) {
                $this->assertSame(Level::expectedOnDay($meal->day), $meal->dish->level);
            }
            $this->assertSame([], $menu->warnings);
        }
    }

    public function testFallsBackToTheOtherLevelAndSaysSo(): void
    {
        // Que des plats rapides : les repas du week-end ne peuvent pas être élaborés.
        $dishes = [];
        for ($id = 1; $id <= 20; ++$id) {
            $dishes[] = DishFactory::dish($id, Season::Hybrid, level: Level::Quick, categoryId: $id % 8 + 1);
        }

        $menu = $this->generate($dishes, self::ALL, self::ALL, VegetarianShare::None, self::SUMMER, 1);

        $this->assertTrue($menu->isComplete());
        $fallbacks = array_filter($menu->warnings, static fn (MenuWarning $w): bool => WarningKind::LevelFallback === $w->kind);
        $this->assertCount(4, $fallbacks, 'samedi et dimanche, déjeuner et dîner');
        foreach ($fallbacks as $warning) {
            $this->assertContains($warning->day, [5, 6]);
            $this->assertNotNull($warning->meal);
        }
    }

    public function testNoVegetarianMealsWhenNoneIsRequested(): void
    {
        for ($seed = 1; $seed <= 30; ++$seed) {
            $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::None, self::WINTER, $seed);

            $this->assertSame(0, $this->vegetarianCount($menu));
        }
    }

    public function testAtMostOneVegetarianMealWhenSomeIsRequested(): void
    {
        for ($seed = 1; $seed <= 30; ++$seed) {
            $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::Some, self::WINTER, $seed);

            $this->assertLessThanOrEqual(1, $this->vegetarianCount($menu));
        }
    }

    public function testMoreThanHalfVegetarianWhenMostlyIsRequested(): void
    {
        for ($seed = 1; $seed <= 30; ++$seed) {
            $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::Mostly, self::WINTER, $seed);

            $this->assertGreaterThanOrEqual(8, $this->vegetarianCount($menu));
            $this->assertSame([], $this->warningsOfKind($menu, WarningKind::VegetarianShortfall));
        }
    }

    public function testWarnsWhenThereAreNotEnoughVegetarianDishes(): void
    {
        $dishes = [];
        for ($id = 1; $id <= 20; ++$id) {
            $dishes[] = DishFactory::dish($id, Season::Hybrid, 3 === $id ? DishType::Vegetarian : DishType::Classic, categoryId: $id % 8 + 1);
        }

        $menu = $this->generate($dishes, self::ALL, self::ALL, VegetarianShare::Mostly, self::SUMMER, 1);

        $this->assertSame(1, $this->vegetarianCount($menu));
        $this->assertCount(1, $this->warningsOfKind($menu, WarningKind::VegetarianShortfall));
        $this->assertTrue($menu->isComplete());
    }

    public function testKeepsTheShareOfHybridsBetweenThirtyAndFortyPercent(): void
    {
        for ($seed = 1; $seed <= 30; ++$seed) {
            $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::Some, self::WINTER, $seed);

            $hybrids = count(array_filter($menu->meals, static fn (MenuMeal $m): bool => Season::Hybrid === $m->dish->season));
            $this->assertGreaterThanOrEqual(0.3 * 14, $hybrids);
            $this->assertLessThanOrEqual(0.4 * 14, $hybrids);
        }
    }

    public function testAvoidsTheSameMainCategoryAtLunchAndDinnerOfTheSameDay(): void
    {
        for ($seed = 1; $seed <= 30; ++$seed) {
            $menu = $this->generate(DishFactory::catalogue(), self::ALL, self::ALL, VegetarianShare::Some, self::WINTER, $seed);

            $byDay = [];
            foreach ($menu->meals as $meal) {
                $byDay[$meal->day][] = $meal->dish->categoryId;
            }
            foreach ($byDay as $categories) {
                $this->assertSame($categories, array_values(array_unique($categories)));
            }
        }
    }

    public function testFavoriteDishesAreDrawnMoreOftenThanRareOnes(): void
    {
        $rare = DishFactory::dish(1, Season::Hybrid, weight: 1, categoryId: 1);
        $favorite = DishFactory::dish(2, Season::Hybrid, weight: 3, categoryId: 2);
        $count = ['rare' => 0, 'favorite' => 0];

        for ($seed = 1; $seed <= 400; ++$seed) {
            $menu = $this->generate([$rare, $favorite], [3], [3], VegetarianShare::None, self::SUMMER, $seed);
            // Le plat tiré au déjeuner est le seul à dépendre du poids : le dîner prend l'autre.
            ++$count[1 === $menu->mealsOfKind(MealKind::Lunch)[0]->dish->id ? 'rare' : 'favorite'];
        }

        $this->assertGreaterThan($count['rare'] * 2, $count['favorite']);
    }

    public function testReportsMissingMealsInsteadOfBreakingARule(): void
    {
        $dishes = [
            DishFactory::dish(1, Season::Hybrid),
            DishFactory::dish(2, Season::Hybrid, categoryId: 2),
            DishFactory::dish(3, Season::AutumnWinter, categoryId: 3),
        ];

        $menu = $this->generate($dishes, [0, 5], [1, 5], VegetarianShare::None, self::SUMMER, 1);

        $this->assertCount(2, $menu->meals);
        $this->assertFalse($menu->isComplete());
        $this->assertCount(2, $this->warningsOfKind($menu, WarningKind::MissingDish));
        $ids = array_map(static fn (MenuMeal $meal): int => $meal->dish->id, $menu->meals);
        $this->assertSame([1, 2], [min($ids), max($ids)], 'ni doublon, ni plat de la saison opposée');
    }

    public function testAnEmptyBaseGivesAnEmptyMenuWithWarnings(): void
    {
        $menu = $this->generate([], [0, 2, 4], [1, 3], VegetarianShare::Some, self::SUMMER, 1);

        $this->assertSame([], $menu->meals);
        $this->assertCount(5, $this->warningsOfKind($menu, WarningKind::MissingDish));
    }

    public function testASeedGivesTheSameMenu(): void
    {
        $dishes = DishFactory::catalogue();

        $first = $this->generate($dishes, [0, 1, 2, 4, 6], [0, 1, 2, 3, 5, 6], VegetarianShare::Some, self::SUMMER, 42);
        $second = $this->generate($dishes, [0, 1, 2, 4, 6], [0, 1, 2, 3, 5, 6], VegetarianShare::Some, self::SUMMER, 42);

        $this->assertEquals($first, $second);
    }

    public function testPlansMealsInTheOrderOfTheWeek(): void
    {
        $menu = $this->generate(DishFactory::catalogue(), [6, 0], [2, 0, 5], VegetarianShare::Some, self::WINTER, 1);

        $order = array_map(static fn (MenuMeal $meal): string => $meal->day.$meal->kind->value, $menu->meals);
        $this->assertSame(['0dejeuner', '0diner', '2diner', '5diner', '6dejeuner'], $order);
    }

    public function testLunchesAndDinnersFollowTheirOwnDays(): void
    {
        $menu = $this->generate(DishFactory::catalogue(), [0], [5, 6], VegetarianShare::Some, self::WINTER, 1);

        $this->assertSame([0], $this->daysOf($menu, MealKind::Lunch));
        $this->assertSame([5, 6], $this->daysOf($menu, MealKind::Dinner));
        $this->assertSame(Level::Quick, $menu->mealsOfKind(MealKind::Lunch)[0]->dish->level);
    }

    public function testGeneratesQuicklyWithTwoHundredDishes(): void
    {
        $dishes = DishFactory::catalogue(200);

        $start = microtime(true);
        for ($seed = 1; $seed <= 20; ++$seed) {
            $this->generate($dishes, self::ALL, self::ALL, VegetarianShare::Mostly, self::WINTER, $seed);
        }

        $this->assertLessThan(3.0, (microtime(true) - $start) / 20);
    }

    public function testRegeneratingOneMealLeavesTheOthersUntouched(): void
    {
        $dishes = DishFactory::catalogue();
        $request = $this->request(self::ALL, self::ALL, VegetarianShare::Some, self::WINTER);

        foreach ([1, 2, 3, 4, 5] as $seed) {
            $menu = $this->generator($seed)->generate($dishes, $request);
            $replaced = $this->generator($seed + 100)->regenerate($dishes, $request, $menu->meals, 2, MealKind::Dinner);

            $this->assertNotNull($replaced);
            $this->assertCount(14, $replaced->meals);
            foreach ($menu->meals as $index => $meal) {
                if (2 === $meal->day && MealKind::Dinner === $meal->kind) {
                    $this->assertNotSame($meal->dish->id, $replaced->meals[$index]->dish->id);

                    continue;
                }
                $this->assertSame($meal, $replaced->meals[$index]);
            }
            $ids = array_map(static fn (MenuMeal $meal): int => $meal->dish->id, $replaced->meals);
            $this->assertSame($ids, array_values(array_unique($ids)), 'aucun doublon');
        }
    }

    public function testRegeneratingRespectsTheHardRulesAgainstTheOtherMeals(): void
    {
        $dishes = DishFactory::catalogue();
        $request = $this->request([0, 1, 2], [0, 1, 2], VegetarianShare::None, self::SUMMER);
        $menu = $this->generator(1)->generate($dishes, $request);

        for ($seed = 1; $seed <= 30; ++$seed) {
            $replaced = $this->generator($seed)->regenerate($dishes, $request, $menu->meals, 1, MealKind::Lunch);

            $this->assertNotNull($replaced);
            $dish = $replaced->mealsOfKind(MealKind::Lunch)[1]->dish;
            $this->assertTrue($dish->fitsLunch);
            $this->assertTrue($dish->season->acceptedIn(Season::SpringSummer));
            $this->assertNotSame(DishType::Vegetarian, $dish->type, 'aucun végétarien demandé');
        }
    }

    public function testRegeneratingFillsAMissingMeal(): void
    {
        $dishes = DishFactory::catalogue();
        $request = $this->request([0, 1], [0], VegetarianShare::Some, self::WINTER);
        $menu = $this->generator(1)->generate($dishes, $request);
        $withoutDinner = array_values(array_filter($menu->meals, static fn (MenuMeal $meal): bool => MealKind::Dinner !== $meal->kind));

        $filled = $this->generator(1)->regenerate($dishes, $request, $withoutDinner, 0, MealKind::Dinner);

        $this->assertNotNull($filled);
        $this->assertCount(3, $filled->meals);
        $this->assertTrue($filled->isComplete());
    }

    public function testRegeneratingReturnsNullWhenNoOtherDishFits(): void
    {
        $dishes = [DishFactory::dish(1, Season::Hybrid), DishFactory::dish(2, Season::Hybrid, categoryId: 2)];
        $request = $this->request([0], [1], VegetarianShare::None, self::SUMMER);
        $menu = $this->generator(1)->generate($dishes, $request);

        $this->assertNull($this->generator(1)->regenerate($dishes, $request, $menu->meals, 0, MealKind::Lunch));
    }

    public function testPlacingAChosenDishReplacesTheMealAndEvaluatesTheMenu(): void
    {
        $dishes = DishFactory::catalogue();
        $request = $this->request([0, 5], [0], VegetarianShare::None, self::SUMMER);
        $menu = $this->generator(1)->generate($dishes, $request);
        $chosen = DishFactory::dish(900, Season::AutumnWinter, DishType::Vegetarian, Level::Elaborate);

        $placed = $this->generator(1)->place($menu->meals, $request, new MenuMeal(0, MealKind::Lunch, $chosen));

        $this->assertCount(3, $placed->meals);
        $this->assertSame($chosen, $placed->meals[0]->dish);
        $this->assertSame($menu->meals[1], $placed->meals[1]);
        $kinds = array_map(static fn (MenuWarning $w): WarningKind => $w->kind, $placed->warnings);
        $this->assertContains(WarningKind::OutOfSeason, $kinds);
        $this->assertContains(WarningKind::LevelFallback, $kinds);
        $this->assertContains(WarningKind::VegetarianExcess, $kinds);
    }

    public function testPlacingADishInAnEmptySlotKeepsThePlanningOrder(): void
    {
        $request = $this->request([0, 2], [1], VegetarianShare::None, self::SUMMER);
        $meals = [new MenuMeal(0, MealKind::Lunch, DishFactory::dish(1)), new MenuMeal(2, MealKind::Lunch, DishFactory::dish(2))];

        $placed = $this->generator(1)->place($meals, $request, new MenuMeal(1, MealKind::Dinner, DishFactory::dish(3)));

        $this->assertSame(['0dejeuner', '1diner', '2dejeuner'], array_map(static fn (MenuMeal $m): string => $m->day.$m->kind->value, $placed->meals));
        $this->assertTrue($placed->isComplete());
    }

    private function generator(int $seed): MenuGenerator
    {
        return new MenuGenerator(new SeasonResolver(), new Randomizer(new Mt19937($seed)), new MenuEvaluator(new SeasonResolver()));
    }

    /**
     * @param list<int> $lunchDays
     * @param list<int> $dinnerDays
     */
    private function request(array $lunchDays, array $dinnerDays, VegetarianShare $share, string $date): MenuRequest
    {
        return new MenuRequest($lunchDays, $dinnerDays, $share, new \DateTimeImmutable($date));
    }

    /**
     * @param list<Dish> $dishes
     * @param list<int>  $lunchDays
     * @param list<int>  $dinnerDays
     */
    private function generate(array $dishes, array $lunchDays, array $dinnerDays, VegetarianShare $share, string $date, int $seed): Menu
    {
        $generator = new MenuGenerator(new SeasonResolver(), new Randomizer(new Mt19937($seed)), new MenuEvaluator(new SeasonResolver()));

        return $generator->generate($dishes, new MenuRequest($lunchDays, $dinnerDays, $share, new \DateTimeImmutable($date)));
    }

    /**
     * @return list<int>
     */
    private function daysOf(Menu $menu, MealKind $kind): array
    {
        return array_map(static fn (MenuMeal $meal): int => $meal->day, $menu->mealsOfKind($kind));
    }

    private function vegetarianCount(Menu $menu): int
    {
        return count(array_filter($menu->meals, static fn (MenuMeal $meal): bool => DishType::Vegetarian === $meal->dish->type));
    }

    /**
     * @return list<MenuWarning>
     */
    private function warningsOfKind(Menu $menu, WarningKind $kind): array
    {
        return array_values(array_filter($menu->warnings, static fn (MenuWarning $w): bool => $kind === $w->kind));
    }
}

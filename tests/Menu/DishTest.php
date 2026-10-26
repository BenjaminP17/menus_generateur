<?php

namespace App\Tests\Menu;

use App\Menu\Dish;
use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\MealKind;
use App\Menu\Season;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dish::class)]
final class DishTest extends TestCase
{
    /**
     * @return array<string, array{bool, bool}>
     */
    public static function mealProvider(): array
    {
        return [
            'plat du midi seulement' => [true, false],
            'plat du soir seulement' => [false, true],
            'plat des deux repas' => [true, true],
        ];
    }

    #[DataProvider('mealProvider')]
    public function testFitsReflectsTheAllowedMeals(bool $fitsLunch, bool $fitsDinner): void
    {
        $dish = $this->dish($fitsLunch, $fitsDinner);

        $this->assertSame($fitsLunch, $dish->fits(MealKind::Lunch));
        $this->assertSame($fitsDinner, $dish->fits(MealKind::Dinner));
    }

    private function dish(bool $fitsLunch, bool $fitsDinner): Dish
    {
        $date = new \DateTimeImmutable('2026-10-25T12:00:00+00:00');

        return new Dish(
            1,
            'Gratin de courgettes',
            'gratin de courgettes',
            $fitsLunch,
            $fitsDinner,
            Season::SpringSummer,
            DishType::Vegetarian,
            Level::Quick,
            6,
            2,
            $date,
            $date,
        );
    }
}

<?php

namespace App\Tests\Menu;

use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\MealKind;
use App\Menu\Season;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fou contre une divergence entre les enums et les CHECK de config/schema.sql.
 */
#[CoversClass(Season::class)]
#[CoversClass(DishType::class)]
#[CoversClass(Level::class)]
#[CoversClass(MealKind::class)]
final class DishEnumsTest extends TestCase
{
    public function testSeasonValuesMatchTheSchema(): void
    {
        $this->assertSame(['printemps_ete', 'automne_hiver', 'hybride'], Season::values());
    }

    public function testDishTypeValuesMatchTheSchema(): void
    {
        $this->assertSame(['classique', 'vegetarien'], DishType::values());
    }

    public function testLevelValuesMatchTheSchema(): void
    {
        $this->assertSame(['rapide', 'elabore'], Level::values());
    }

    public function testMealKindValuesMatchTheSchema(): void
    {
        $this->assertSame(['dejeuner', 'diner'], MealKind::values());
    }

    /**
     * @return iterable<string, array{Season|DishType|Level|MealKind}>
     */
    public static function caseProvider(): iterable
    {
        foreach ([...Season::cases(), ...DishType::cases(), ...Level::cases(), ...MealKind::cases()] as $case) {
            yield $case::class.' : '.$case->value => [$case];
        }
    }

    #[DataProvider('caseProvider')]
    public function testEveryCaseHasALabel(Season|DishType|Level|MealKind $case): void
    {
        $this->assertNotSame('', $case->label());
    }
}

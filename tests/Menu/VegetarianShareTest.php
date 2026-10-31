<?php

namespace App\Tests\Menu;

use App\Menu\VegetarianShare;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VegetarianShare::class)]
final class VegetarianShareTest extends TestCase
{
    /**
     * @return array<string, array{VegetarianShare, int, int, int}>
     */
    public static function boundsProvider(): array
    {
        return [
            'aucun sur 14' => [VegetarianShare::None, 14, 0, 0],
            'quelques-uns sur 14' => [VegetarianShare::Some, 14, 0, 1],
            'quelques-uns sur 1' => [VegetarianShare::Some, 1, 0, 1],
            'majoritairement sur 14 : au moins 8' => [VegetarianShare::Mostly, 14, 8, 14],
            'majoritairement sur 13 : au moins 7' => [VegetarianShare::Mostly, 13, 7, 13],
            'majoritairement sur 2 : les deux' => [VegetarianShare::Mostly, 2, 2, 2],
            'majoritairement sur 1' => [VegetarianShare::Mostly, 1, 1, 1],
        ];
    }

    #[DataProvider('boundsProvider')]
    public function testBoundsDependOnTheTotalOfMeals(VegetarianShare $share, int $total, int $min, int $max): void
    {
        $this->assertSame($min, $share->min($total));
        $this->assertSame($max, $share->max($total));
    }

    public function testMostlyIsMoreThanHalfOfTheMeals(): void
    {
        for ($total = 2; $total <= 14; ++$total) {
            $this->assertGreaterThan($total / 2, VegetarianShare::Mostly->min($total));
        }
    }
}

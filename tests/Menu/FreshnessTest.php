<?php

namespace App\Tests\Menu;

use App\Menu\Freshness;
use App\Tests\Support\DishFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Freshness::class)]
final class FreshnessTest extends TestCase
{
    private const WEEK = '2026-10-26';

    public function testWithoutHistoryTheWeightIsUnchanged(): void
    {
        $dish = DishFactory::dish(1, weight: 3);

        $this->assertSame(3, Freshness::none()->weight($dish));
        $this->assertSame(3, Freshness::fromHistory([], new \DateTimeImmutable(self::WEEK))->weight($dish));
    }

    public function testADishNeverProposedIsFavoured(): void
    {
        $freshness = $this->history([2 => '2026-10-19']);

        $this->assertGreaterThan($freshness->weight(DishFactory::dish(2)), $freshness->weight(DishFactory::dish(1)));
    }

    public function testTheMoreRecentTheProposalTheLighterTheDish(): void
    {
        $freshness = $this->history([
            1 => '2026-10-19', // il y a 1 semaine
            2 => '2026-10-05', // il y a 3 semaines
            3 => '2026-09-07', // il y a 7 semaines
        ]);

        $weights = array_map(static fn (int $id): int => $freshness->weight(DishFactory::dish($id)), [1, 2, 3]);

        $this->assertLessThan($weights[1], $weights[0]);
        $this->assertLessThan($weights[2], $weights[1]);
        $this->assertLessThan($freshness->weight(DishFactory::dish(99)), $weights[2]);
    }

    public function testADishProposedBeyondTheRetentionCountsAsNeverProposed(): void
    {
        $freshness = $this->history([1 => '2026-08-31', 2 => '2026-10-19']); // 8 semaines, 1 semaine

        $this->assertSame($freshness->weight(DishFactory::dish(99)), $freshness->weight(DishFactory::dish(1)));
    }

    public function testThePreferenceWeightStillCounts(): void
    {
        $freshness = $this->history([1 => '2026-10-19']);

        $this->assertSame(
            3 * $freshness->weight(DishFactory::dish(1, weight: 1)),
            $freshness->weight(DishFactory::dish(1, weight: 3)),
        );
    }

    public function testAFutureWeekWeighsLikeAPastWeekOfTheSameGap(): void
    {
        $freshness = $this->history([1 => '2026-11-02', 2 => '2026-10-19']);

        $this->assertSame($freshness->weight(DishFactory::dish(2)), $freshness->weight(DishFactory::dish(1)));
    }

    /**
     * @param array<int, string> $lastProposed lundi de la dernière proposition, par identifiant de plat
     */
    private function history(array $lastProposed): Freshness
    {
        return Freshness::fromHistory(
            array_map(static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date), $lastProposed),
            new \DateTimeImmutable(self::WEEK),
        );
    }
}

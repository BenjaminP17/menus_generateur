<?php

namespace App\Tests\Menu;

use App\Menu\Season;
use App\Menu\SeasonResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeasonResolver::class)]
#[CoversClass(Season::class)]
final class SeasonResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, Season}>
     */
    public static function dateProvider(): array
    {
        return [
            'dernier jour de février' => ['2026-02-28', Season::AutumnWinter],
            'premier jour de mars' => ['2026-03-01', Season::SpringSummer],
            'milieu de l\'été' => ['2026-07-14', Season::SpringSummer],
            'dernier jour d\'août' => ['2026-08-31', Season::SpringSummer],
            'premier jour de septembre' => ['2026-09-01', Season::AutumnWinter],
            'fin d\'année' => ['2026-12-31', Season::AutumnWinter],
            'premier janvier' => ['2027-01-01', Season::AutumnWinter],
        ];
    }

    #[DataProvider('dateProvider')]
    public function testResolvesTheSeasonOfADate(string $date, Season $expected): void
    {
        $this->assertSame($expected, (new SeasonResolver())->resolve(new \DateTimeImmutable($date)));
    }

    public function testAHybridDishIsAcceptedInEverySeason(): void
    {
        $this->assertTrue(Season::Hybrid->acceptedIn(Season::SpringSummer));
        $this->assertTrue(Season::Hybrid->acceptedIn(Season::AutumnWinter));
    }

    public function testTheOppositeSeasonIsExcluded(): void
    {
        $this->assertTrue(Season::SpringSummer->acceptedIn(Season::SpringSummer));
        $this->assertFalse(Season::SpringSummer->acceptedIn(Season::AutumnWinter));
        $this->assertFalse(Season::AutumnWinter->acceptedIn(Season::SpringSummer));
    }
}

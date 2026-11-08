<?php

namespace App\Tests\Menu;

use App\Menu\Week;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Week::class)]
final class WeekTest extends TestCase
{
    public function testGivesTheMondayOfTheWeek(): void
    {
        // Du lundi 26 octobre au dimanche 1er novembre 2026 : même semaine, même lundi.
        foreach (['2026-10-26', '2026-10-28 15:30', '2026-11-01 23:59'] as $date) {
            $this->assertSame('2026-10-26 00:00', Week::mondayOf(new \DateTimeImmutable($date))->format('Y-m-d H:i'), $date);
        }
        $this->assertSame('2026-11-02', Week::mondayOf(new \DateTimeImmutable('2026-11-02'))->format('Y-m-d'));
    }
}

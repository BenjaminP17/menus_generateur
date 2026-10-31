<?php

namespace App\Tests\Menu;

use App\Menu\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Level::class)]
final class LevelTest extends TestCase
{
    public function testExpectedLevelFollowsTheWeek(): void
    {
        foreach ([0, 1, 2, 3, 4] as $weekday) {
            $this->assertSame(Level::Quick, Level::expectedOnDay($weekday));
        }
        foreach ([5, 6] as $weekendDay) {
            $this->assertSame(Level::Elaborate, Level::expectedOnDay($weekendDay));
        }
    }
}

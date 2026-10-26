<?php

namespace App\Tests\Support;

use App\Menu\Clock;

/**
 * Horloge figée, que l'on peut avancer : permet de vérifier cree_le et modifie_le.
 */
final class FixedClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $iso = '2026-10-25T12:00:00+00:00')
    {
        $this->now = new \DateTimeImmutable($iso);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}

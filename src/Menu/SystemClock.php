<?php

namespace App\Menu;

/**
 * Horloge réelle, toujours en UTC.
 *
 * Le fuseau est fixé pour que les horodatages ISO 8601 stockés se comparent correctement comme
 * des chaînes : des décalages mélangés casseraient les tris sur les dates.
 */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

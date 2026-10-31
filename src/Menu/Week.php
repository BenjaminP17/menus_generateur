<?php

namespace App\Menu;

/**
 * Semaine du menu, repérée par son lundi (PRD §5.4 : la semaine commence le lundi).
 */
final class Week
{
    /**
     * Lundi, à minuit, de la semaine qui contient la date.
     */
    public static function mondayOf(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify('monday this week')->setTime(0, 0);
    }
}

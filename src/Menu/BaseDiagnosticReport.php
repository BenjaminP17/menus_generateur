<?php

namespace App\Menu;

/**
 * Bilan de la base de plats pour une semaine complète (PRD §3, « Base suffisante »).
 */
final class BaseDiagnosticReport
{
    /**
     * @param list<MealDiagnostic> $meals un bilan par repas
     */
    public function __construct(
        public readonly Season $season,
        public readonly array $meals,
        public readonly int $distinctDishes,
        public readonly bool $sufficient,
    ) {
    }
}

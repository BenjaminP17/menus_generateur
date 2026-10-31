<?php

namespace App\Menu;

/**
 * Bilan de la base pour un repas (déjeuner ou dîner) : combien de plats sont utilisables dans la
 * saison en cours, et combien il en faut pour une semaine complète.
 */
final class MealDiagnostic
{
    public function __construct(
        public readonly MealKind $kind,
        public readonly int $available,
        public readonly int $availableQuick,
        public readonly int $availableElaborate,
        public readonly bool $sufficient,
        public readonly bool $levelsCovered,
    ) {
    }
}

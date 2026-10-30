<?php

namespace App\Menu;

/**
 * Réglages d'une génération de menu (PRD §5.2), déjà validés.
 *
 * L'utilisateur choisit les jours de déjeuner et les jours de dîner, indépendamment : le nombre
 * de repas du menu en découle (PRD §7, décision 1).
 */
final class MenuRequest
{
    /**
     * @param list<int> $lunchDays  jours de déjeuner, triés et distincts (0 pour lundi, jusqu'à 6 pour dimanche)
     * @param list<int> $dinnerDays jours de dîner, triés et distincts
     */
    public function __construct(
        public readonly array $lunchDays,
        public readonly array $dinnerDays,
        public readonly VegetarianShare $vegetarianShare,
        public readonly \DateTimeImmutable $referenceDate,
    ) {
    }

    public function totalMeals(): int
    {
        return count($this->lunchDays) + count($this->dinnerDays);
    }

    /**
     * @return list<int>
     */
    public function daysFor(MealKind $kind): array
    {
        return match ($kind) {
            MealKind::Lunch => $this->lunchDays,
            MealKind::Dinner => $this->dinnerDays,
        };
    }
}

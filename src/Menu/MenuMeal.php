<?php

namespace App\Menu;

/**
 * Un repas du menu : un plat affecté à un jour et à un repas, avec son origine (proposé par le
 * générateur ou choisi à la main).
 */
final class MenuMeal
{
    /**
     * @param int $day 0 pour lundi, jusqu'à 6 pour dimanche
     */
    public function __construct(
        public readonly int $day,
        public readonly MealKind $kind,
        public readonly Dish $dish,
        public readonly ProposalOrigin $origin = ProposalOrigin::Suggestion,
    ) {
    }
}

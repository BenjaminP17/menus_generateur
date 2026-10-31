<?php

namespace App\Menu;

/**
 * Un repas du menu : un plat affecté à un jour et à un repas.
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
    ) {
    }
}

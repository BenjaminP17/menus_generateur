<?php

namespace App\Menu;

/**
 * Un plat affecté à un repas, tel que le navigateur le renvoie : par identifiant de plat.
 */
final class MenuPlacement
{
    /**
     * @param int $day 0 pour lundi, jusqu'à 6 pour dimanche
     */
    public function __construct(
        public readonly int $day,
        public readonly MealKind $kind,
        public readonly int $dishId,
    ) {
    }
}

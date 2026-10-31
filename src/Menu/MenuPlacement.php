<?php

namespace App\Menu;

/**
 * Un plat affecté à un repas, tel que le navigateur le renvoie : par identifiant de plat, avec
 * son origine (proposé par le générateur ou choisi à la main), que le journal des propositions
 * garde à la validation du menu.
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
        public readonly ProposalOrigin $origin = ProposalOrigin::Suggestion,
    ) {
    }
}

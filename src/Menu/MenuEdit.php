<?php

namespace App\Menu;

/**
 * Modification demandée sur un menu en cours, déjà validée (PRD §5.3).
 *
 * Le menu n'est pas stocké côté serveur : le navigateur le renvoie à chaque demande, repas par
 * repas. `target` désigne le repas à changer ; `dishId` est le plat choisi à la main pour ce
 * repas, absent quand on demande une nouvelle suggestion.
 */
final class MenuEdit
{
    /**
     * @param list<MenuPlacement> $placements repas du menu en cours
     */
    public function __construct(
        public readonly array $placements,
        public readonly ?int $targetDay,
        public readonly ?MealKind $targetKind,
        public readonly ?int $dishId,
    ) {
    }

    public function hasTarget(): bool
    {
        return null !== $this->targetDay && null !== $this->targetKind;
    }
}

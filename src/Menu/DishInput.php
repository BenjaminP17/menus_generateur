<?php

namespace App\Menu;

/**
 * Plat validé, pas encore enregistré : ni identifiant ni dates.
 *
 * Produit par DishInputValidator et consommé par DishRepository. Le nom est déjà nettoyé
 * (voir DishNameNormalizer::clean()).
 */
final class DishInput
{
    public function __construct(
        public readonly string $name,
        public readonly bool $fitsLunch,
        public readonly bool $fitsDinner,
        public readonly Season $season,
        public readonly DishType $type,
        public readonly Level $level,
        public readonly int $categoryId,
        public readonly int $weight,
    ) {
    }
}

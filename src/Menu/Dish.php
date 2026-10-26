<?php

namespace App\Menu;

/**
 * Plat tel qu'il est stocké.
 *
 * Objet immuable, sans dépendance au framework ni à PDO : il est réutilisé tel quel par le
 * générateur de menus. L'identifiant et les dates sont toujours connus ; un plat pas encore
 * enregistré est un DishInput, pas un Dish.
 */
final class Dish
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $normalizedName,
        public readonly bool $fitsLunch,
        public readonly bool $fitsDinner,
        public readonly Season $season,
        public readonly DishType $type,
        public readonly Level $level,
        public readonly int $categoryId,
        public readonly int $weight,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Le plat peut-il être proposé à ce repas ?
     *
     * Seul endroit qui connaît la correspondance entre un repas et les deux colonnes
     * convient_dejeuner / convient_diner.
     */
    public function fits(MealKind $kind): bool
    {
        return match ($kind) {
            MealKind::Lunch => $this->fitsLunch,
            MealKind::Dinner => $this->fitsDinner,
        };
    }
}

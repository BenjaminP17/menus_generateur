<?php

namespace App\Menu;

/**
 * Menu généré : les repas, dans l'ordre du planning, et les avertissements.
 *
 * Quand la base est suffisante, il contient exactement le nombre de déjeuners et de dîners
 * demandé. Sinon les repas manquants sont absents et signalés par un avertissement
 * MissingDish, plutôt que de violer une règle.
 */
final class Menu
{
    /**
     * @param list<MenuMeal>    $meals    triés par jour, déjeuner avant dîner
     * @param list<MenuWarning> $warnings
     */
    public function __construct(
        public readonly array $meals,
        public readonly array $warnings,
    ) {
    }

    /**
     * @return list<MenuMeal>
     */
    public function mealsOfKind(MealKind $kind): array
    {
        return array_values(array_filter(
            $this->meals,
            static fn (MenuMeal $meal): bool => $meal->kind === $kind,
        ));
    }

    public function isComplete(): bool
    {
        foreach ($this->warnings as $warning) {
            if (WarningKind::MissingDish === $warning->kind) {
                return false;
            }
        }

        return true;
    }
}

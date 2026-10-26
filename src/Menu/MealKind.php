<?php

namespace App\Menu;

/**
 * Repas de la journée.
 *
 * Le stockage d'un plat utilise deux booléens (convient_dejeuner, convient_diner) ; cet enum
 * sert à les interroger sans les manipuler directement (voir Dish::fits()), ce dont le filtrage
 * du générateur a besoin. Les valeurs reprennent le CHECK de la colonne proposition.repas.
 */
enum MealKind: string
{
    case Lunch = 'dejeuner';
    case Dinner = 'diner';

    public function label(): string
    {
        return match ($this) {
            self::Lunch => 'déjeuner',
            self::Dinner => 'dîner',
        };
    }

    /**
     * @return list<string> valeurs acceptées, pour les messages de validation
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

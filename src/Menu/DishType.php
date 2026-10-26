<?php

namespace App\Menu;

/**
 * Type d'un plat (PRD §5.1) : végétarien ou non.
 *
 * Les valeurs reprennent le CHECK de la colonne plat.type.
 */
enum DishType: string
{
    case Classic = 'classique';
    case Vegetarian = 'vegetarien';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'classique',
            self::Vegetarian => 'végétarien',
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

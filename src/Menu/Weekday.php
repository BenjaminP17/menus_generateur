<?php

namespace App\Menu;

/**
 * Jour de la semaine, du lundi au dimanche (PRD §5.4 : la semaine commence le lundi).
 *
 * Les valeurs sont les noms français utilisés par l'API. Le menu manipule l'index du jour
 * (0 pour lundi, jusqu'à 6 pour dimanche), que les cas de l'enum donnent dans l'ordre.
 */
enum Weekday: string
{
    case Monday = 'lundi';
    case Tuesday = 'mardi';
    case Wednesday = 'mercredi';
    case Thursday = 'jeudi';
    case Friday = 'vendredi';
    case Saturday = 'samedi';
    case Sunday = 'dimanche';

    public function index(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /**
     * Nom du jour à partir de son index (0 pour lundi, jusqu'à 6 pour dimanche).
     */
    public static function label(int $index): string
    {
        return self::cases()[$index]->value;
    }

    /**
     * @return list<string> valeurs acceptées, pour les messages de validation
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

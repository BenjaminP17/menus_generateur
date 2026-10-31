<?php

namespace App\Menu;

/**
 * Réglage de la proportion de repas végétariens d'un menu (PRD §5.2).
 *
 * Les bornes dépendent du nombre total de repas du menu (déjeuners + dîners demandés).
 */
enum VegetarianShare: string
{
    case None = 'aucun';
    case Some = 'quelques_uns';
    case Mostly = 'majoritairement';

    public function label(): string
    {
        return match ($this) {
            self::None => 'aucun',
            self::Some => 'quelques-uns',
            self::Mostly => 'majoritairement',
        };
    }

    /**
     * Nombre minimal de repas végétariens. « Majoritairement » : plus de la moitié du menu
     * (8 repas sur 14).
     */
    public function min(int $total): int
    {
        return match ($this) {
            self::None, self::Some => 0,
            self::Mostly => min($total, intdiv($total, 2) + 1),
        };
    }

    /**
     * Nombre maximal de repas végétariens. « Quelques-uns » : un seul par menu (PRD §5.2).
     */
    public function max(int $total): int
    {
        return match ($this) {
            self::None => 0,
            self::Some => min($total, 1),
            self::Mostly => $total,
        };
    }

    /**
     * Nombre de repas végétariens visé par le tirage : le minimum, sauf pour « quelques-uns »
     * où l'on vise le maximum.
     */
    public function target(int $total): int
    {
        return match ($this) {
            self::Some => $this->max($total),
            default => $this->min($total),
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

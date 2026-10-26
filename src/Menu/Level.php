<?php

namespace App\Menu;

/**
 * Niveau de préparation d'un plat (PRD §5.1).
 *
 * Choisi à la main par l'utilisateur, jamais calculé à partir d'un temps de préparation.
 * Les valeurs reprennent le CHECK de la colonne plat.niveau.
 */
enum Level: string
{
    case Quick = 'rapide';
    case Elaborate = 'elabore';

    public function label(): string
    {
        return match ($this) {
            self::Quick => 'rapide',
            self::Elaborate => 'élaboré',
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

<?php

namespace App\Menu;

/**
 * Saison d'un plat (PRD §5.4).
 *
 * Les valeurs reprennent le CHECK de la colonne plat.saison : elles sont stockées telles quelles.
 */
enum Season: string
{
    case SpringSummer = 'printemps_ete';
    case AutumnWinter = 'automne_hiver';
    case Hybrid = 'hybride';

    /**
     * Libellé affichable, pour les messages et le frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::SpringSummer => 'printemps-été',
            self::AutumnWinter => 'automne-hiver',
            self::Hybrid => 'hybride',
        };
    }

    /**
     * Un plat de cette saison peut-il figurer dans un menu de la saison en cours ?
     *
     * Les hybrides sont acceptés en toute saison ; la saison opposée est exclue (PRD §5.4).
     */
    public function acceptedIn(self $current): bool
    {
        return self::Hybrid === $this || $this === $current;
    }

    /**
     * @return list<string> valeurs acceptées, pour les messages de validation
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

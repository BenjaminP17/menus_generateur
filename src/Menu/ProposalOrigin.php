<?php

namespace App\Menu;

/**
 * D'où vient un plat du menu validé : proposé par le générateur, ou choisi à la main.
 *
 * Les valeurs sont celles de la colonne `proposition.origine` et du champ `origine` de l'API.
 */
enum ProposalOrigin: string
{
    case Suggestion = 'suggestion';
    case FreeChoice = 'choix_libre';

    /**
     * @return list<string> valeurs acceptées, pour les messages de validation
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

<?php

namespace App\Menu;

/**
 * Déduit la saison d'une date (PRD §5.4, hémisphère nord uniquement).
 *
 * Printemps-été : mars à août. Automne-hiver : septembre à février. Le résultat n'est jamais
 * hybride : « hybride » qualifie un plat, pas une période.
 */
final class SeasonResolver
{
    public function resolve(\DateTimeInterface $date): Season
    {
        $month = (int) $date->format('n');

        return $month >= 3 && $month <= 8 ? Season::SpringSummer : Season::AutumnWinter;
    }
}

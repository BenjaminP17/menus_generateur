<?php

namespace App\Menu;

/**
 * Nature d'un avertissement d'un menu : il signale une règle non respectée, faute de plats ou
 * parce que l'utilisateur a choisi un plat à la main, sans jamais bloquer le menu (PRD §5.3 et
 * §5.4).
 */
enum WarningKind: string
{
    case MissingDish = 'plat_manquant';
    case LevelFallback = 'niveau_de_repli';
    case VegetarianShortfall = 'vegetarien_insuffisant';
    case VegetarianExcess = 'vegetarien_excessif';
    case DuplicateDish = 'plat_en_double';
    case OutOfSeason = 'hors_saison';
    case MealNotAllowed = 'repas_non_autorise';
}

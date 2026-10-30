<?php

namespace App\Menu;

/**
 * Nature d'un avertissement du générateur : il signale une règle non respectée faute de plats,
 * sans bloquer le menu (PRD §5.3 et §5.4).
 */
enum WarningKind: string
{
    case MissingDish = 'plat_manquant';
    case LevelFallback = 'niveau_de_repli';
    case VegetarianShortfall = 'vegetarien_insuffisant';
}

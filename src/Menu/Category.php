<?php

namespace App\Menu;

/**
 * Catégorie principale d'un plat (PRD §5.1).
 *
 * Liste fermée stockée en table de référence, pour pouvoir évoluer sans modifier le schéma.
 * Sert à la règle de variété entre le déjeuner et le dîner d'un même jour.
 */
final class Category
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
    ) {
    }
}

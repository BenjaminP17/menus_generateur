<?php

namespace App\Menu;

/**
 * Avertissement attaché à un menu : le message est destiné à l'utilisateur.
 */
final class MenuWarning
{
    public function __construct(
        public readonly WarningKind $kind,
        public readonly string $message,
        public readonly ?MealKind $meal = null,
        public readonly ?int $day = null,
    ) {
    }
}

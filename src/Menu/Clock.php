<?php

namespace App\Menu;

/**
 * Source de l'heure courante, injectable pour rendre les tests déterministes.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}

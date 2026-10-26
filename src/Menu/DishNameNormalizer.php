<?php

namespace App\Menu;

use function Symfony\Component\String\u;

/**
 * Nettoie le nom affiché d'un plat et calcule sa clé d'unicité (plat.nom_normalise).
 *
 * Seul point du code qui normalise un nom : l'insertion, la modification et la détection de
 * doublon passent par ici, sinon l'unicité devient incohérente.
 */
final class DishNameNormalizer
{
    /**
     * Nom tel qu'il sera stocké et affiché : espaces de bord retirés et suites d'espaces
     * ramenées à un seul. La casse et les accents sont conservés.
     */
    public function clean(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Clé d'unicité : minuscules, sans accent ni ligature (« Quîche » donne « quiche »,
     * « Œufs » donne « oeufs »).
     *
     * COLLATE NOCASE ne suffirait pas : SQLite ignore la casse mais pas les accents
     * (voir doc/modelisation-bdd.md).
     */
    public function normalize(string $name): string
    {
        return u($this->clean($name))->ascii()->lower()->toString();
    }
}

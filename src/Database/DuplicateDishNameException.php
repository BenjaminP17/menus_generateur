<?php

namespace App\Database;

use App\Menu\Dish;

/**
 * Un plat porte déjà ce nom, à la normalisation près (voir plat.nom_normalise).
 */
final class DuplicateDishNameException extends \RuntimeException
{
    public function __construct(private readonly Dish $existing)
    {
        parent::__construct(sprintf('Le plat « %s » existe déjà.', $existing->name));
    }

    /**
     * Le plat déjà enregistré, pour composer le message destiné à l'utilisateur.
     */
    public function existing(): Dish
    {
        return $this->existing;
    }
}

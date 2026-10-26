<?php

namespace App\Controller;

use App\Menu\Dish;

/**
 * Traduit un Dish vers le contrat JSON de l'API : clés en français, alignées sur les colonnes
 * de la table (voir doc/api.md).
 *
 * La traduction vit ici, et non sur Dish, pour que src/Menu/ reste utilisable par le générateur
 * sans rien connaître du vocabulaire HTTP. nom_normalise n'est pas exposé : c'est une clé
 * technique interne.
 */
final class DishPayload
{
    /**
     * @return array<string, bool|int|string>
     */
    public static function fromDish(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'nom' => $dish->name,
            'convient_dejeuner' => $dish->fitsLunch,
            'convient_diner' => $dish->fitsDinner,
            'saison' => $dish->season->value,
            'type' => $dish->type->value,
            'niveau' => $dish->level->value,
            'categorie_principale_id' => $dish->categoryId,
            'poids' => $dish->weight,
            'cree_le' => $dish->createdAt->format(\DateTimeInterface::ATOM),
            'modifie_le' => $dish->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<Dish> $dishes
     *
     * @return list<array<string, bool|int|string>>
     */
    public static function fromDishes(array $dishes): array
    {
        return array_map(static fn (Dish $dish): array => self::fromDish($dish), $dishes);
    }
}

<?php

namespace App\Controller;

use App\Menu\Menu;
use App\Menu\MenuMeal;
use App\Menu\MenuWarning;
use App\Menu\Weekday;

/**
 * Traduit un Menu vers le contrat JSON de l'API : clés en français (voir doc/api.md).
 *
 * Comme DishPayload, la traduction vit ici pour que src/Menu/ ignore le vocabulaire HTTP.
 */
final class MenuPayload
{
    /**
     * @return array{repas: list<array<string, mixed>>, avertissements: list<array<string, mixed>>, complet: bool}
     */
    public static function fromMenu(Menu $menu): array
    {
        return [
            'repas' => array_map(static fn (MenuMeal $meal): array => [
                'jour' => Weekday::label($meal->day),
                'repas' => $meal->kind->value,
                'plat' => DishPayload::fromDish($meal->dish),
            ], $menu->meals),
            'avertissements' => array_map(static fn (MenuWarning $warning): array => self::fromWarning($warning), $menu->warnings),
            'complet' => $menu->isComplete(),
        ];
    }

    /**
     * `jour` et `repas` n'apparaissent que pour un avertissement rattaché à un repas.
     *
     * @return array<string, mixed>
     */
    private static function fromWarning(MenuWarning $warning): array
    {
        $payload = ['type' => $warning->kind->value, 'message' => $warning->message];
        if (null !== $warning->day && null !== $warning->meal) {
            $payload['jour'] = Weekday::label($warning->day);
            $payload['repas'] = $warning->meal->value;
        }

        return $payload;
    }
}

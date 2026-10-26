<?php

namespace App\Tests\Support;

use App\Database\DishRepository;
use App\Menu\DishInput;
use App\Menu\DishInputValidator;
use App\Menu\DishNameNormalizer;
use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\Season;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plats fictifs pour les tests.
 *
 * Aucune base de plats réelle n'est versionnée (voir ROADMAP.md, « Données de test »).
 */
final class DishFactory
{
    /**
     * Payload JSON valide. Une valeur null dans $overrides retire la clé du payload, ce qui
     * permet de tester les champs absents.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        $payload = [
            'nom' => 'Gratin de courgettes',
            'convient_dejeuner' => true,
            'convient_diner' => true,
            'saison' => 'printemps_ete',
            'type' => 'vegetarien',
            'niveau' => 'rapide',
            'categorie_principale_id' => 6,
            'poids' => 2,
        ];

        foreach ($overrides as $key => $value) {
            if (null === $value) {
                unset($payload[$key]);

                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * Entrée validée, construite depuis le payload pour que les tests partagent les mêmes règles.
     *
     * @param array<string, mixed> $overrides
     */
    public static function input(array $overrides = []): DishInput
    {
        return (new DishInputValidator(new DishNameNormalizer()))
            ->validate(self::payload($overrides))
            ->input();
    }

    /**
     * Entrée construite directement, pour les cas qu'un payload ne peut pas exprimer.
     */
    public static function rawInput(
        string $name,
        Season $season = Season::SpringSummer,
        DishType $type = DishType::Vegetarian,
        Level $level = Level::Quick,
        int $categoryId = 6,
        int $weight = 2,
        bool $fitsLunch = true,
        bool $fitsDinner = true,
    ): DishInput {
        return new DishInput($name, $fitsLunch, $fitsDinner, $season, $type, $level, $categoryId, $weight);
    }

    public static function repository(\PDO $pdo, ?FixedClock $clock = null): DishRepository
    {
        return new DishRepository($pdo, new DishNameNormalizer(), $clock ?? new FixedClock());
    }

    /**
     * Requête HTTP portant un corps JSON, pour les tests de contrôleur.
     *
     * @param array<string, mixed>|string $payload une chaîne est envoyée telle quelle, ce qui
     *                                             permet de tester un corps mal formé
     */
    public static function request(string $method, string $uri, array|string $payload = []): Request
    {
        $body = is_string($payload) ? $payload : json_encode($payload, \JSON_THROW_ON_ERROR);

        return Request::create($uri, $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }
}

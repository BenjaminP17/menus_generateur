<?php

namespace App\Tests\Controller;

use App\Controller\MenuController;
use App\Controller\MenuPayload;
use App\Database\DishRepository;
use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\Season;
use App\Tests\Support\DishFactory;
use App\Tests\Support\FunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MenuController::class)]
#[CoversClass(MenuPayload::class)]
final class MenuControllerTest extends FunctionalTestCase
{
    private const SUMMER = '2026-07-14';

    public function testEveryRouteRequiresASession(): void
    {
        foreach (['/api/menu', '/api/menu/regenerer', '/api/menu/choisir'] as $path) {
            $this->post($path, []);

            self::assertSame(401, $this->client->getResponse()->getStatusCode(), $path);
        }
    }

    public function testGeneratesTheMealsOfTheChosenDays(): void
    {
        $this->login();
        $this->seed(30);

        $this->post('/api/menu', $this->settings(['lundi', 'mardi', 'samedi'], ['lundi', 'dimanche']));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $menu = $this->menu();
        self::assertTrue($menu['complet']);
        self::assertSame(
            ['lundi/dejeuner', 'lundi/diner', 'mardi/dejeuner', 'samedi/dejeuner', 'dimanche/diner'],
            array_map(static fn (array $meal): string => $meal['jour'].'/'.$meal['repas'], $menu['repas']),
        );
        $ids = array_column(array_column($menu['repas'], 'plat'), 'id');
        self::assertSame($ids, array_unique($ids), 'aucun plat en double');
        self::assertArrayNotHasKey('nom_normalise', $menu['repas'][0]['plat']);
    }

    public function testAnEmptyBaseGivesAnIncompleteMenu(): void
    {
        $this->login();

        $this->post('/api/menu', $this->settings(['lundi'], ['lundi', 'mardi']));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $menu = $this->menu();
        self::assertSame([], $menu['repas']);
        self::assertFalse($menu['complet']);
        self::assertSame(['plat_manquant', 'plat_manquant', 'plat_manquant'], array_column($menu['avertissements'], 'type'));
        self::assertSame('lundi', $menu['avertissements'][0]['jour']);
        self::assertSame('dejeuner', $menu['avertissements'][0]['repas']);
    }

    public function testRejectsInvalidSettings(): void
    {
        $this->login();

        $this->post('/api/menu', ['jours_dejeuners' => [], 'jours_diners' => ['lundi'], 'vegetarien' => 'beaucoup']);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $body = $this->responseBody();
        self::assertSame('Les données envoyées sont invalides.', $body['erreur']);
        self::assertSame(['jours_dejeuners', 'vegetarien'], array_keys($body['champs']));
    }

    public function testRejectsAMalformedBody(): void
    {
        $this->login();

        $this->client->request('POST', '/api/menu', server: ['CONTENT_TYPE' => 'application/json'], content: '{oops');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testGeneratesQuicklyWithTwoHundredDishes(): void
    {
        $this->login();
        $this->seed(200);

        $start = microtime(true);
        $this->post('/api/menu', $this->settings(
            ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
        ));
        $elapsed = microtime(true) - $start;

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(14, $this->menu()['repas']);
        self::assertLessThan(3.0, $elapsed);
    }

    public function testRegeneratingOneMealLeavesTheOthersUntouched(): void
    {
        $this->login();
        $this->seed(40);
        $settings = $this->settings(['lundi', 'mardi', 'mercredi'], ['lundi', 'mardi', 'mercredi']);
        $this->post('/api/menu', $settings);
        $before = $this->menu()['repas'];

        $this->post('/api/menu/regenerer', $settings + [
            'repas' => $this->placements($before),
            'cible' => ['jour' => 'mardi', 'repas' => 'diner'],
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $after = $this->menu()['repas'];
        self::assertCount(6, $after);
        foreach ($before as $index => $meal) {
            if ('mardi' === $meal['jour'] && 'diner' === $meal['repas']) {
                self::assertNotSame($meal['plat']['id'], $after[$index]['plat']['id'], 'un autre plat est proposé');

                continue;
            }
            self::assertSame($meal, $after[$index]);
        }
        $ids = array_column(array_column($after, 'plat'), 'id');
        self::assertSame($ids, array_unique($ids));
    }

    public function testRegeneratingWithoutATargetRebuildsTheWholeMenu(): void
    {
        $this->login();
        $this->seed(40);
        $settings = $this->settings(['lundi', 'mardi'], ['mercredi']);

        $this->post('/api/menu/regenerer', $settings + ['repas' => []]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(3, $this->menu()['repas']);
    }

    public function testRegeneratingAMealWithNoAlternativeIsRefused(): void
    {
        $this->login();
        $first = $this->createDish('Plat A');
        $second = $this->createDish('Plat B');

        $this->post('/api/menu/regenerer', $this->settings(['lundi'], ['mardi']) + [
            'repas' => [
                ['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => $first],
                ['jour' => 'mardi', 'repas' => 'diner', 'plat_id' => $second],
            ],
            'cible' => ['jour' => 'lundi', 'repas' => 'dejeuner'],
        ]);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        self::assertSame('Aucun autre plat ne convient pour ce repas.', $this->responseBody()['erreur']);
    }

    public function testADeletedDishLeavesAGapThatIsReported(): void
    {
        $this->login();
        $this->seed(20);

        $this->post('/api/menu/regenerer', $this->settings(['lundi'], ['mardi']) + [
            'repas' => [
                ['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => 999999],
                ['jour' => 'mardi', 'repas' => 'diner', 'plat_id' => 1],
            ],
            'cible' => ['jour' => 'mardi', 'repas' => 'diner'],
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $menu = $this->menu();
        self::assertCount(1, $menu['repas']);
        self::assertFalse($menu['complet']);
        self::assertSame('plat_manquant', $menu['avertissements'][0]['type']);
        self::assertSame('lundi', $menu['avertissements'][0]['jour']);
    }

    public function testRegenerationRejectsABadMenuOrTarget(): void
    {
        $this->login();
        $settings = $this->settings(['lundi'], ['mardi']);

        $this->post('/api/menu/regenerer', $settings + [
            'repas' => [['jour' => 'samedi', 'repas' => 'dejeuner', 'plat_id' => 1]],
            'cible' => ['jour' => 'mardi', 'repas' => 'dejeuner'],
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame(['repas', 'cible'], array_keys($this->responseBody()['champs']));
    }

    public function testAFreeChoiceIsAcceptedWithWarnings(): void
    {
        $this->login();
        $quick = $this->createDish('Plat rapide');
        $winter = $this->createDish('Soupe d\'hiver', season: Season::AutumnWinter, level: Level::Elaborate);
        $other = $this->createDish('Autre plat', level: Level::Elaborate);

        $this->post('/api/menu/choisir', $this->settings(['lundi'], ['lundi']) + [
            'repas' => [
                ['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => $quick],
                ['jour' => 'lundi', 'repas' => 'diner', 'plat_id' => $other],
            ],
            'cible' => ['jour' => 'lundi', 'repas' => 'dejeuner'],
            'plat_id' => $winter,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $menu = $this->menu();
        self::assertSame([$winter, $other], array_column(array_column($menu['repas'], 'plat'), 'id'));
        $byType = [];
        foreach ($menu['avertissements'] as $warning) {
            $byType[$warning['type']][] = $warning['repas'];
        }
        self::assertSame(['dejeuner'], $byType['hors_saison']);
        self::assertSame(['dejeuner', 'diner'], $byType['niveau_de_repli'], 'lundi attend du rapide');
        self::assertTrue($menu['complet']);
    }

    public function testAFreeChoiceCanDuplicateADishAndSaysSo(): void
    {
        $this->login();
        $dish = $this->createDish('Plat unique');
        $other = $this->createDish('Plat voisin');

        $this->post('/api/menu/choisir', $this->settings(['lundi'], ['mardi']) + [
            'repas' => [
                ['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => $dish],
                ['jour' => 'mardi', 'repas' => 'diner', 'plat_id' => $other],
            ],
            'cible' => ['jour' => 'mardi', 'repas' => 'diner'],
            'plat_id' => $dish,
        ]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $types = array_column($this->menu()['avertissements'], 'type');
        self::assertSame(['plat_en_double'], $types);
    }

    public function testAFreeChoiceNeedsATargetAndAKnownDish(): void
    {
        $this->login();
        $settings = $this->settings(['lundi'], ['mardi']);

        $this->post('/api/menu/choisir', $settings + ['repas' => []]);
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame(['cible', 'plat_id'], array_keys($this->responseBody()['champs']));

        $this->post('/api/menu/choisir', $settings + [
            'cible' => ['jour' => 'lundi', 'repas' => 'dejeuner'],
            'plat_id' => 424242,
        ]);
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame('Plat introuvable.', $this->responseBody()['champs']['plat_id']);
    }

    /**
     * @param list<string> $lunchDays
     * @param list<string> $dinnerDays
     *
     * @return array<string, mixed>
     */
    private function settings(array $lunchDays, array $dinnerDays): array
    {
        return [
            'jours_dejeuners' => $lunchDays,
            'jours_diners' => $dinnerDays,
            'vegetarien' => 'aucun',
            'date_reference' => self::SUMMER,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $path, array $payload): void
    {
        $this->client->request('POST', $path, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{repas: list<array<string, mixed>>, avertissements: list<array<string, mixed>>, complet: bool}
     */
    private function menu(): array
    {
        /** @var array{repas: list<array<string, mixed>>, avertissements: list<array<string, mixed>>, complet: bool} $menu */
        $menu = $this->responseBody()['menu'];

        return $menu;
    }

    /**
     * @param list<array<string, mixed>> $meals
     *
     * @return list<array{jour: mixed, repas: mixed, plat_id: mixed}>
     */
    private function placements(array $meals): array
    {
        return array_map(static fn (array $meal): array => [
            'jour' => $meal['jour'],
            'repas' => $meal['repas'],
            'plat_id' => $meal['plat']['id'],
        ], $meals);
    }

    private function createDish(string $name, Season $season = Season::Hybrid, Level $level = Level::Quick): int
    {
        return static::getContainer()->get(DishRepository::class)
            ->create(DishFactory::rawInput($name, $season, DishType::Classic, $level))
            ->id;
    }

    /**
     * Base fictive tirée du catalogue de DishFactory, sans ses plats qui ne conviennent à aucun
     * repas (la base les refuse).
     */
    private function seed(int $size): void
    {
        $repository = static::getContainer()->get(DishRepository::class);
        foreach (DishFactory::catalogue($size) as $dish) {
            if (!$dish->fitsLunch && !$dish->fitsDinner) {
                continue;
            }

            $repository->create(DishFactory::rawInput(
                $dish->name,
                $dish->season,
                $dish->type,
                $dish->level,
                $dish->categoryId,
                $dish->weight,
                $dish->fitsLunch,
                $dish->fitsDinner,
            ));
        }
    }
}

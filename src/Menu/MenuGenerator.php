<?php

namespace App\Menu;

use Random\Randomizer;

/**
 * Compose un menu de sept jours à partir d'une liste de plats (PRD §5.4).
 *
 * Classe PHP pure : ni base ni framework. Le tirage passe par un Randomizer injecté, que les
 * tests amorcent avec une graine pour obtenir des menus reproductibles.
 *
 * Contraintes dures, jamais violées : repas autorisé, saison courante ou hybride, aucun plat
 * deux fois dans le menu, plafond de repas végétariens. Si aucun plat ne les respecte, le repas
 * reste vide et un avertissement le signale.
 *
 * Priorités douces, appliquées de la plus à la moins importante et ignorées une à une quand la
 * base ne permet pas de les tenir : repas végétariens à atteindre, niveau du jour, part
 * d'hybrides, part de végétariens visée, catégorie principale différente entre déjeuner et dîner
 * du même jour.
 *
 * Les repas tombent aux jours choisis par l'utilisateur, séparément pour les déjeuners et les
 * dîners. Le facteur de fraîcheur arrive avec le journal des propositions (ROADMAP, jalon 5) :
 * ici le tirage ne dépend que de `poids`.
 */
final class MenuGenerator
{
    /** Part visée de repas hybrides : le PRD demande 30 à 40 %. */
    private const HYBRID_SHARE = 0.35;

    public function __construct(
        private readonly SeasonResolver $seasonResolver,
        private readonly Randomizer $randomizer,
    ) {
    }

    /**
     * @param list<Dish> $dishes
     */
    public function generate(array $dishes, MenuRequest $request): Menu
    {
        $season = $this->seasonResolver->resolve($request->referenceDate);
        $pool = array_values(array_filter(
            $dishes,
            static fn (Dish $dish): bool => $dish->season->acceptedIn($season),
        ));

        $slots = $this->slots($request);
        $total = count($slots);
        $vegetarianMin = $request->vegetarianShare->min($total);
        $vegetarianMax = $request->vegetarianShare->max($total);
        $vegetarianTarget = $request->vegetarianShare->target($total);
        $hybridTarget = (int) round(self::HYBRID_SHARE * $total);

        /** @var list<MenuMeal> $meals */
        $meals = [];
        /** @var list<MenuWarning> $warnings */
        $warnings = [];
        /** @var array<int, true> $usedIds */
        $usedIds = [];
        /** @var array<int, int> $categoryOfDay catégorie du premier repas posé un jour donné */
        $categoryOfDay = [];
        $vegetarianCount = 0;
        $hybridCount = 0;

        foreach ($slots as $index => [$day, $kind]) {
            $remaining = $total - $index;

            $candidates = array_values(array_filter(
                $pool,
                static fn (Dish $dish): bool => !isset($usedIds[$dish->id])
                    && $dish->fits($kind)
                    && ($vegetarianCount < $vegetarianMax || DishType::Vegetarian !== $dish->type),
            ));

            $mustBeVegetarian = $vegetarianMin - $vegetarianCount >= $remaining;
            $wantsVegetarian = $this->coin($vegetarianTarget - $vegetarianCount, $remaining);
            $wantsHybrid = $this->coin($hybridTarget - $hybridCount, $remaining);
            $expectedLevel = Level::expectedOnDay($day);

            // Du plus au moins important : le tirage relâche les derniers en premier.
            $preferences = [];
            if ($mustBeVegetarian) {
                $preferences[] = static fn (Dish $dish): bool => DishType::Vegetarian === $dish->type;
            }
            $preferences[] = static fn (Dish $dish): bool => $dish->level === $expectedLevel;
            $preferences[] = static fn (Dish $dish): bool => $wantsHybrid === (Season::Hybrid === $dish->season);
            if (!$mustBeVegetarian) {
                $preferences[] = static fn (Dish $dish): bool => $wantsVegetarian === (DishType::Vegetarian === $dish->type);
            }
            if (isset($categoryOfDay[$day])) {
                $sameDayCategory = $categoryOfDay[$day];
                $preferences[] = static fn (Dish $dish): bool => $dish->categoryId !== $sameDayCategory;
            }

            $dish = $this->draw($candidates, $preferences);
            if (null === $dish) {
                $warnings[] = new MenuWarning(
                    WarningKind::MissingDish,
                    sprintf(
                        'La base ne contient aucun plat disponible pour le %s du %s.',
                        $kind->label(),
                        Weekday::label($day),
                    ),
                    $kind,
                    $day,
                );

                continue;
            }

            $usedIds[$dish->id] = true;
            $categoryOfDay[$day] ??= $dish->categoryId;
            $vegetarianCount += DishType::Vegetarian === $dish->type ? 1 : 0;
            $hybridCount += Season::Hybrid === $dish->season ? 1 : 0;
            $meals[] = new MenuMeal($day, $kind, $dish);

            if ($dish->level !== $expectedLevel) {
                $warnings[] = new MenuWarning(
                    WarningKind::LevelFallback,
                    sprintf(
                        'Pas assez de plats %s pour le %s du %s : un plat %s est proposé.',
                        $expectedLevel->label(),
                        $kind->label(),
                        Weekday::label($day),
                        $dish->level->label(),
                    ),
                    $kind,
                    $day,
                );
            }
        }

        if ($vegetarianCount < $vegetarianMin) {
            $warnings[] = new MenuWarning(
                WarningKind::VegetarianShortfall,
                sprintf(
                    'La base ne contient pas assez de plats végétariens : %d sur %d repas au lieu de %d au moins.',
                    $vegetarianCount,
                    $total,
                    $vegetarianMin,
                ),
            );
        }

        return new Menu($meals, $warnings);
    }

    /**
     * Repas à composer, aux jours choisis, triés par jour puis déjeuner avant dîner.
     *
     * @return list<array{int, MealKind}>
     */
    private function slots(MenuRequest $request): array
    {
        $slots = [];
        foreach ([MealKind::Lunch, MealKind::Dinner] as $kind) {
            foreach ($request->daysFor($kind) as $day) {
                $slots[] = [$day, $kind];
            }
        }

        usort($slots, static fn (array $a, array $b): int => [$a[0], $a[1]->value] <=> [$b[0], $b[1]->value]);

        return $slots;
    }

    /**
     * Tire vrai avec la probabilité $needed / $remaining : répartit sur les repas restants le
     * nombre de plats d'une sorte qu'il reste à placer.
     */
    private function coin(int $needed, int $remaining): bool
    {
        if ($needed <= 0) {
            return false;
        }

        return $needed >= $remaining || $this->randomizer->getInt(1, $remaining) <= $needed;
    }

    /**
     * Tire un plat pondéré par `poids` parmi les candidats, après avoir restreint la liste par
     * chaque préférence dans l'ordre d'importance : une préférence qui ne laisserait aucun
     * candidat est ignorée, et les suivantes s'appliquent quand même.
     *
     * @param list<Dish>                 $candidates
     * @param list<callable(Dish): bool> $preferences de la plus à la moins importante
     */
    private function draw(array $candidates, array $preferences): ?Dish
    {
        if ([] === $candidates) {
            return null;
        }

        foreach ($preferences as $preference) {
            $narrowed = array_values(array_filter($candidates, $preference));
            if ([] !== $narrowed) {
                $candidates = $narrowed;
            }
        }

        return $this->weightedPick($candidates);
    }

    /**
     * @param non-empty-list<Dish> $dishes
     */
    private function weightedPick(array $dishes): Dish
    {
        $sum = array_sum(array_map(static fn (Dish $dish): int => $dish->weight, $dishes));
        $roll = $this->randomizer->getInt(1, $sum);

        foreach ($dishes as $dish) {
            $roll -= $dish->weight;
            if ($roll <= 0) {
                return $dish;
            }
        }

        return $dishes[array_key_last($dishes)];
    }
}

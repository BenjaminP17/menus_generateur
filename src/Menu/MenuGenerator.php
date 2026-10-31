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
 *
 * Aucun menu n'est conservé : pour changer un repas (`regenerate`, `place`), l'appelant redonne
 * le menu en cours, et les autres repas restent tels quels.
 */
final class MenuGenerator
{
    /** Part visée de repas hybrides : le PRD demande 30 à 40 %. */
    private const HYBRID_SHARE = 0.35;

    public function __construct(
        private readonly SeasonResolver $seasonResolver,
        private readonly Randomizer $randomizer,
        private readonly MenuEvaluator $evaluator,
    ) {
    }

    /**
     * @param list<Dish> $dishes
     */
    public function generate(array $dishes, MenuRequest $request): Menu
    {
        $pool = $this->seasonalPool($dishes, $request);
        $slots = $request->slots();
        $total = count($slots);

        /** @var list<MenuMeal> $meals */
        $meals = [];
        foreach ($slots as $index => [$day, $kind]) {
            $dish = $this->pick($pool, $request, $meals, $day, $kind, $total - $index, []);
            if (null !== $dish) {
                $meals[] = new MenuMeal($day, $kind, $dish);
            }
        }

        return new Menu($meals, $this->evaluator->evaluate($meals, $request));
    }

    /**
     * Change le plat d'un seul repas, sans toucher aux autres (PRD §5.3). Le nouveau plat
     * respecte les mêmes contraintes que celles du tirage initial, vis-à-vis des autres repas
     * du menu, et diffère du plat actuel.
     *
     * @param list<Dish>     $dishes
     * @param list<MenuMeal> $meals  menu en cours
     *
     * @return Menu|null le menu avec le repas changé, ou null si aucun autre plat ne convient
     */
    public function regenerate(array $dishes, MenuRequest $request, array $meals, int $day, MealKind $kind): ?Menu
    {
        $others = $this->withoutSlot($meals, $day, $kind);
        $current = array_map(
            static fn (MenuMeal $meal): int => $meal->dish->id,
            array_values(array_filter($meals, static fn (MenuMeal $meal): bool => $meal->day === $day && $meal->kind === $kind)),
        );

        $dish = $this->pick($this->seasonalPool($dishes, $request), $request, $others, $day, $kind, 1, $current);
        if (null === $dish) {
            return null;
        }

        return $this->place($meals, $request, new MenuMeal($day, $kind, $dish));
    }

    /**
     * Pose un plat choisi à la main. Le choix est libre : une règle non respectée donne un
     * avertissement, jamais un refus.
     *
     * @param list<MenuMeal> $meals menu en cours
     */
    public function place(array $meals, MenuRequest $request, MenuMeal $chosen): Menu
    {
        $meals = $this->withoutSlot($meals, $chosen->day, $chosen->kind);
        $meals[] = $chosen;
        usort($meals, static fn (MenuMeal $a, MenuMeal $b): int => [$a->day, $a->kind->value] <=> [$b->day, $b->kind->value]);

        return new Menu($meals, $this->evaluator->evaluate($meals, $request));
    }

    /**
     * @param list<Dish> $dishes
     *
     * @return list<Dish>
     */
    private function seasonalPool(array $dishes, MenuRequest $request): array
    {
        $season = $this->seasonResolver->resolve($request->referenceDate);

        return array_values(array_filter(
            $dishes,
            static fn (Dish $dish): bool => $dish->season->acceptedIn($season),
        ));
    }

    /**
     * @param list<MenuMeal> $meals
     *
     * @return list<MenuMeal>
     */
    private function withoutSlot(array $meals, int $day, MealKind $kind): array
    {
        return array_values(array_filter(
            $meals,
            static fn (MenuMeal $meal): bool => $meal->day !== $day || $meal->kind !== $kind,
        ));
    }

    /**
     * Tire le plat d'un repas, compte tenu des repas déjà posés.
     *
     * @param list<Dish>     $pool      plats de la saison
     * @param list<MenuMeal> $placed    repas déjà posés ailleurs dans le menu
     * @param int            $remaining repas restant à placer, celui-ci compris
     * @param list<int>      $excluded  plats à écarter en plus de ceux déjà posés
     */
    private function pick(array $pool, MenuRequest $request, array $placed, int $day, MealKind $kind, int $remaining, array $excluded): ?Dish
    {
        $total = $request->totalMeals();
        $vegetarianMin = $request->vegetarianShare->min($total);
        $vegetarianMax = $request->vegetarianShare->max($total);
        $vegetarianTarget = $request->vegetarianShare->target($total);
        $hybridTarget = (int) round(self::HYBRID_SHARE * $total);

        /** @var array<int, true> $usedIds */
        $usedIds = array_fill_keys($excluded, true);
        $vegetarianCount = 0;
        $hybridCount = 0;
        $sameDayCategory = null;
        foreach ($placed as $meal) {
            $usedIds[$meal->dish->id] = true;
            $vegetarianCount += DishType::Vegetarian === $meal->dish->type ? 1 : 0;
            $hybridCount += Season::Hybrid === $meal->dish->season ? 1 : 0;
            if ($meal->day === $day) {
                $sameDayCategory ??= $meal->dish->categoryId;
            }
        }

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
        if (null !== $sameDayCategory) {
            $preferences[] = static fn (Dish $dish): bool => $dish->categoryId !== $sameDayCategory;
        }

        return $this->draw($candidates, $preferences);
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

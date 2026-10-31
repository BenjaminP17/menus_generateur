<?php

namespace App\Menu;

/**
 * Passe un menu en revue et signale les règles non respectées (PRD §5.3 et §5.4).
 *
 * Le générateur ne viole jamais une contrainte dure, mais le choix libre d'un plat le permet :
 * l'évaluateur sert aux deux. Il ne bloque rien, il ne fait que produire les avertissements.
 */
final class MenuEvaluator
{
    public function __construct(private readonly SeasonResolver $seasonResolver)
    {
    }

    /**
     * @param list<MenuMeal> $meals repas du menu, au plus un par jour et par repas
     *
     * @return list<MenuWarning> par repas dans l'ordre du planning, puis les écarts sur le menu entier
     */
    public function evaluate(array $meals, MenuRequest $request): array
    {
        $season = $this->seasonResolver->resolve($request->referenceDate);

        /** @var array<string, MenuMeal> $bySlot */
        $bySlot = [];
        foreach ($meals as $meal) {
            $bySlot[$meal->day.$meal->kind->value] = $meal;
        }

        /** @var list<MenuWarning> $warnings */
        $warnings = [];
        /** @var array<int, true> $seen */
        $seen = [];
        $vegetarianCount = 0;

        foreach ($request->slots() as [$day, $kind]) {
            $meal = $bySlot[$day.$kind->value] ?? null;
            $when = sprintf('%s du %s', $kind->label(), Weekday::label($day));

            if (null === $meal) {
                $warnings[] = new MenuWarning(
                    WarningKind::MissingDish,
                    sprintf('Aucun plat disponible pour le %s.', $when),
                    $kind,
                    $day,
                );

                continue;
            }

            $dish = $meal->dish;
            $vegetarianCount += DishType::Vegetarian === $dish->type ? 1 : 0;

            if (isset($seen[$dish->id])) {
                $warnings[] = new MenuWarning(
                    WarningKind::DuplicateDish,
                    sprintf('« %s » est déjà au menu de la semaine (%s).', $dish->name, $when),
                    $kind,
                    $day,
                );
            }
            $seen[$dish->id] = true;

            if (!$dish->fits($kind)) {
                $warnings[] = new MenuWarning(
                    WarningKind::MealNotAllowed,
                    sprintf('« %s » n\'est pas prévu pour le %s.', $dish->name, $when),
                    $kind,
                    $day,
                );
            }

            if (!$dish->season->acceptedIn($season)) {
                $warnings[] = new MenuWarning(
                    WarningKind::OutOfSeason,
                    sprintf('« %s » est un plat %s, hors saison pour le %s.', $dish->name, $dish->season->label(), $when),
                    $kind,
                    $day,
                );
            }

            $expectedLevel = Level::expectedOnDay($day);
            if ($dish->level !== $expectedLevel) {
                $warnings[] = new MenuWarning(
                    WarningKind::LevelFallback,
                    sprintf(
                        'Le %s est un plat %s, alors qu\'un plat %s est attendu ce jour-là.',
                        $when,
                        $dish->level->label(),
                        $expectedLevel->label(),
                    ),
                    $kind,
                    $day,
                );
            }
        }

        $total = $request->totalMeals();
        $vegetarianMin = $request->vegetarianShare->min($total);
        $vegetarianMax = $request->vegetarianShare->max($total);

        if ($vegetarianCount < $vegetarianMin) {
            $warnings[] = new MenuWarning(
                WarningKind::VegetarianShortfall,
                sprintf(
                    'Le menu compte %d repas végétariens sur %d, au lieu de %d au moins.',
                    $vegetarianCount,
                    $total,
                    $vegetarianMin,
                ),
            );
        }

        if ($vegetarianCount > $vegetarianMax) {
            $warnings[] = new MenuWarning(
                WarningKind::VegetarianExcess,
                sprintf(
                    'Le menu compte %d repas végétariens sur %d, alors que le réglage « %s » en autorise %d au plus.',
                    $vegetarianCount,
                    $total,
                    $request->vegetarianShare->label(),
                    $vegetarianMax,
                ),
            );
        }

        return $warnings;
    }
}

<?php

namespace App\Menu;

/**
 * Indique, pour chaque repas, si la base contient assez de plats pour une semaine complète
 * (PRD §3 et §5.3) : sept déjeuners et sept dîners, tous différents.
 *
 * Un plat qui convient aux deux repas ne peut servir qu'une fois. La base suffit donc si chaque
 * repas dispose d'au moins sept plats et si, en tout, il y a au moins quatorze plats distincts.
 * Le niveau est un constat à part (`levelsCovered`) : la semaine reste possible sans lui, avec
 * un niveau de repli signalé par le générateur.
 */
final class BaseDiagnostic
{
    public const WEEK_MEALS = 7;
    public const WEEKDAYS = 5;
    public const WEEKEND_DAYS = 2;

    public function __construct(private readonly SeasonResolver $seasonResolver)
    {
    }

    /**
     * @param list<Dish> $dishes
     */
    public function diagnose(array $dishes, \DateTimeImmutable $referenceDate): BaseDiagnosticReport
    {
        $season = $this->seasonResolver->resolve($referenceDate);
        $usable = array_values(array_filter(
            $dishes,
            static fn (Dish $dish): bool => $dish->season->acceptedIn($season),
        ));

        $meals = [];
        foreach ([MealKind::Lunch, MealKind::Dinner] as $kind) {
            $forMeal = array_filter($usable, static fn (Dish $dish): bool => $dish->fits($kind));
            $quick = $this->countLevel($forMeal, Level::Quick);
            $elaborate = $this->countLevel($forMeal, Level::Elaborate);

            $meals[] = new MealDiagnostic(
                $kind,
                count($forMeal),
                $quick,
                $elaborate,
                count($forMeal) >= self::WEEK_MEALS,
                $quick >= self::WEEKDAYS && $elaborate >= self::WEEKEND_DAYS,
            );
        }

        $distinct = count($usable);
        $sufficient = $distinct >= 2 * self::WEEK_MEALS;
        foreach ($meals as $meal) {
            $sufficient = $sufficient && $meal->sufficient;
        }

        return new BaseDiagnosticReport($season, $meals, $distinct, $sufficient);
    }

    /**
     * @param array<array-key, Dish> $dishes
     */
    private function countLevel(array $dishes, Level $level): int
    {
        return count(array_filter($dishes, static fn (Dish $dish): bool => $dish->level === $level));
    }
}

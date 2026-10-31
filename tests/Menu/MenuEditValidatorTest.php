<?php

namespace App\Tests\Menu;

use App\Menu\MealKind;
use App\Menu\MenuEdit;
use App\Menu\MenuEditResult;
use App\Menu\MenuEditValidator;
use App\Menu\MenuPlacement;
use App\Menu\MenuRequest;
use App\Menu\VegetarianShare;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuEditValidator::class)]
#[CoversClass(MenuEditResult::class)]
#[CoversClass(MenuEdit::class)]
#[CoversClass(MenuPlacement::class)]
final class MenuEditValidatorTest extends TestCase
{
    public function testReadsTheMealsTheTargetAndTheChosenDish(): void
    {
        $result = $this->validate([
            'repas' => [
                ['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => 4],
                ['jour' => 'samedi', 'repas' => 'diner', 'plat_id' => 9],
            ],
            'cible' => ['jour' => 'samedi', 'repas' => 'diner'],
            'plat_id' => 12,
        ], true, true);

        $this->assertTrue($result->isValid());
        $edit = $result->edit();
        $this->assertEquals([new MenuPlacement(0, MealKind::Lunch, 4), new MenuPlacement(5, MealKind::Dinner, 9)], $edit->placements);
        $this->assertTrue($edit->hasTarget());
        $this->assertSame(5, $edit->targetDay);
        $this->assertSame(MealKind::Dinner, $edit->targetKind);
        $this->assertSame(12, $edit->dishId);
    }

    public function testEverythingIsOptionalWhenNotRequired(): void
    {
        $result = $this->validate([], false, false);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->edit()->placements);
        $this->assertFalse($result->edit()->hasTarget());
        $this->assertNull($result->edit()->dishId);
    }

    public function testTargetAndDishCanBeRequired(): void
    {
        $result = $this->validate([], true, true);

        $this->assertSame(['cible', 'plat_id'], array_keys($result->errors()));
    }

    public function testRejectsMealsOutsideTheChosenDays(): void
    {
        // Le lundi est un jour de déjeuner, pas de dîner ; le mardi n'est pas choisi.
        foreach ([
            ['jour' => 'lundi', 'repas' => 'diner', 'plat_id' => 1],
            ['jour' => 'mardi', 'repas' => 'dejeuner', 'plat_id' => 1],
        ] as $meal) {
            $result = $this->validate(['repas' => [$meal]], false, false);

            $this->assertSame(['repas'], array_keys($result->errors()));
        }
    }

    public function testRejectsMalformedMeals(): void
    {
        $malformed = [
            'pas une liste' => ['lundi' => 1],
            'élément scalaire' => ['lundi'],
            'jour inconnu' => [['jour' => 'lundiii', 'repas' => 'dejeuner', 'plat_id' => 1]],
            'repas inconnu' => [['jour' => 'lundi', 'repas' => 'gouter', 'plat_id' => 1]],
            'plat absent' => [['jour' => 'lundi', 'repas' => 'dejeuner']],
            'plat en texte' => [['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => '1']],
            'plat nul' => [['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => 0]],
            'plat booléen' => [['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => true]],
            'doublon' => [
                ['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => 1],
                ['jour' => 'lundi', 'repas' => 'dejeuner', 'plat_id' => 2],
            ],
        ];

        foreach ($malformed as $label => $meals) {
            $this->assertSame(['repas'], array_keys($this->validate(['repas' => $meals], false, false)->errors()), $label);
        }
    }

    public function testRejectsABadTargetOrDish(): void
    {
        $this->assertSame(['cible'], array_keys($this->validate(['cible' => 'lundi'], false, false)->errors()));
        $this->assertSame(['cible'], array_keys($this->validate(['cible' => ['jour' => 'lundi', 'repas' => 'diner']], false, false)->errors()));
        $this->assertSame(['plat_id'], array_keys($this->validate(['plat_id' => 'abc'], false, false)->errors()));
        $this->assertSame(['plat_id'], array_keys($this->validate(['plat_id' => -3], false, false)->errors()));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validate(array $data, bool $targetRequired, bool $dishRequired): MenuEditResult
    {
        // Déjeuners lundi et samedi, dîners samedi et dimanche.
        $request = new MenuRequest([0, 5], [5, 6], VegetarianShare::Some, new \DateTimeImmutable('2026-07-14'));

        return (new MenuEditValidator())->validate($data, $request, $targetRequired, $dishRequired);
    }
}

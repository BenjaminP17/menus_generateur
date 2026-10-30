<?php

namespace App\Tests\Menu;

use App\Menu\MealKind;
use App\Menu\MenuRequest;
use App\Menu\MenuRequestResult;
use App\Menu\MenuRequestValidator;
use App\Menu\VegetarianShare;
use App\Menu\Weekday;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuRequestValidator::class)]
#[CoversClass(MenuRequestResult::class)]
#[CoversClass(MenuRequest::class)]
#[CoversClass(Weekday::class)]
final class MenuRequestValidatorTest extends TestCase
{
    public function testAcceptsACompletePayload(): void
    {
        $result = $this->validate([
            'jours_dejeuners' => ['samedi', 'lundi', 'mercredi'],
            'jours_diners' => ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            'vegetarien' => 'majoritairement',
            'date_reference' => '2026-04-15',
        ]);

        $this->assertTrue($result->isValid());
        $request = $result->request();
        $this->assertSame([0, 2, 5], $request->lunchDays, 'triés du lundi au dimanche');
        $this->assertSame([0, 1, 2, 3, 4, 5, 6], $request->dinnerDays);
        $this->assertSame([0, 2, 5], $request->daysFor(MealKind::Lunch));
        $this->assertSame(10, $request->totalMeals());
        $this->assertSame(VegetarianShare::Mostly, $request->vegetarianShare);
        $this->assertSame('2026-04-15', $request->referenceDate->format('Y-m-d'));
    }

    public function testAppliesDefaults(): void
    {
        $request = $this->validate(self::days())->request();

        $this->assertSame(VegetarianShare::Some, $request->vegetarianShare);
        $this->assertSame('2026-10-25', $request->referenceDate->format('Y-m-d'));
    }

    public function testNullOptionalFieldsFallBackToDefaults(): void
    {
        $request = $this->validate([
            ...self::days(),
            'vegetarien' => null,
            'date_reference' => null,
        ])->request();

        $this->assertSame(VegetarianShare::Some, $request->vegetarianShare);
        $this->assertSame('2026-10-25', $request->referenceDate->format('Y-m-d'));
    }

    public function testBothDayListsAreRequired(): void
    {
        $result = $this->validate([]);

        $this->assertFalse($result->isValid());
        $this->assertSame(['jours_dejeuners', 'jours_diners'], array_keys($result->errors()));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function badDaysProvider(): array
    {
        return [
            'liste vide' => [[], 'Choisis au moins un jour.'],
            'pas une liste' => ['lundi', 'doit être une liste'],
            'tableau associatif' => [['a' => 'lundi'], 'doit être une liste'],
            'nombre' => [3, 'doit être une liste'],
            'jour inconnu' => [['lundi', 'funday'], 'Jour inconnu'],
            'majuscule' => [['Lundi'], 'Jour inconnu'],
            'pas un texte' => [[1], 'Jour inconnu'],
            'doublon' => [['lundi', 'mardi', 'lundi'], 'plusieurs fois'],
        ];
    }

    #[DataProvider('badDaysProvider')]
    public function testRejectsInvalidDays(mixed $days, string $message): void
    {
        $result = $this->validate(['jours_dejeuners' => $days, 'jours_diners' => ['lundi']]);

        $this->assertSame(['jours_dejeuners'], array_keys($result->errors()));
        $this->assertStringContainsString($message, $result->errors()['jours_dejeuners']);
    }

    public function testAcceptsASingleDayAndEveryDay(): void
    {
        $result = $this->validate(['jours_dejeuners' => ['dimanche'], 'jours_diners' => Weekday::values()]);

        $this->assertTrue($result->isValid());
        $this->assertSame([6], $result->request()->lunchDays);
        $this->assertSame(7, count($result->request()->dinnerDays));
    }

    public function testRejectsAnUnknownShare(): void
    {
        $result = $this->validate([...self::days(), 'vegetarien' => 'beaucoup']);

        $this->assertSame(['vegetarien'], array_keys($result->errors()));
        $this->assertStringContainsString('aucun, quelques_uns, majoritairement', $result->errors()['vegetarien']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function badDateProvider(): array
    {
        return [
            'format français' => ['15/04/2026'],
            'jour inexistant' => ['2026-02-31'],
            'mois inexistant' => ['2026-13-01'],
            'avec heure' => ['2026-04-15T10:00:00'],
            'pas un texte' => [20260415],
        ];
    }

    #[DataProvider('badDateProvider')]
    public function testRejectsAMalformedDate(mixed $date): void
    {
        $result = $this->validate([...self::days(), 'date_reference' => $date]);

        $this->assertSame(['date_reference'], array_keys($result->errors()));
    }

    public function testCollectsAllErrorsInOnePass(): void
    {
        $result = $this->validate(['jours_dejeuners' => [], 'jours_diners' => ['x'], 'vegetarien' => 1, 'date_reference' => 'x']);

        $this->assertSame(
            ['jours_dejeuners', 'jours_diners', 'vegetarien', 'date_reference'],
            array_keys($result->errors()),
        );
    }

    public function testReadingARequestFromAnInvalidResultIsALogicError(): void
    {
        $this->expectException(\LogicException::class);

        $this->validate([])->request();
    }

    /**
     * @return array<string, list<string>>
     */
    private static function days(): array
    {
        return ['jours_dejeuners' => ['lundi'], 'jours_diners' => ['lundi']];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validate(array $data): MenuRequestResult
    {
        return (new MenuRequestValidator(new FixedClock()))->validate($data);
    }
}

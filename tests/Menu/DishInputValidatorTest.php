<?php

namespace App\Tests\Menu;

use App\Menu\DishInputResult;
use App\Menu\DishInputValidator;
use App\Menu\DishNameNormalizer;
use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\Season;
use App\Tests\Support\DishFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DishInputValidator::class)]
#[CoversClass(DishInputResult::class)]
final class DishInputValidatorTest extends TestCase
{
    public function testAcceptsACompletePayload(): void
    {
        $result = $this->validate(DishFactory::payload([
            'nom' => '  Gratin   de courgettes  ',
            'convient_dejeuner' => false,
            'convient_diner' => true,
            'saison' => 'automne_hiver',
            'type' => 'classique',
            'niveau' => 'elabore',
            'categorie_principale_id' => 3,
            'poids' => 1,
        ]));

        $this->assertTrue($result->isValid());
        $input = $result->input();
        $this->assertSame('Gratin de courgettes', $input->name);
        $this->assertFalse($input->fitsLunch);
        $this->assertTrue($input->fitsDinner);
        $this->assertSame(Season::AutumnWinter, $input->season);
        $this->assertSame(DishType::Classic, $input->type);
        $this->assertSame(Level::Elaborate, $input->level);
        $this->assertSame(3, $input->categoryId);
        $this->assertSame(1, $input->weight);
    }

    public function testAppliesThePrdDefaults(): void
    {
        $payload = DishFactory::payload();
        unset($payload['convient_dejeuner'], $payload['convient_diner'], $payload['poids']);

        $input = $this->validate($payload)->input();

        $this->assertTrue($input->fitsLunch);
        $this->assertTrue($input->fitsDinner);
        $this->assertSame(2, $input->weight);
    }

    public function testIgnoresUnknownKeys(): void
    {
        $result = $this->validate(DishFactory::payload(['id' => 42, 'recette' => 'secrète']));

        $this->assertTrue($result->isValid());
    }

    public function testReadingTheInputOfAnInvalidResultFails(): void
    {
        $result = $this->validate(['nom' => 'Quiche']);

        $this->expectException(\LogicException::class);

        $result->input();
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'nom absent' => [['nom' => null], 'nom'],
            'nom vide' => [['nom' => ''], 'nom'],
            'nom uniquement des espaces' => [['nom' => '   '], 'nom'],
            'nom trop long' => [['nom' => str_repeat('a', 121)], 'nom'],
            'nom non textuel' => [['nom' => 42], 'nom'],
            'saison absente' => [['saison' => null], 'saison'],
            'saison inconnue' => [['saison' => 'ete'], 'saison'],
            'saison non textuelle' => [['saison' => 3], 'saison'],
            'type absent' => [['type' => null], 'type'],
            'type inconnu' => [['type' => 'vegan'], 'type'],
            'niveau absent' => [['niveau' => null], 'niveau'],
            'niveau inconnu' => [['niveau' => 'moyen'], 'niveau'],
            'catégorie absente' => [['categorie_principale_id' => null], 'categorie_principale_id'],
            'catégorie non entière' => [['categorie_principale_id' => '6'], 'categorie_principale_id'],
            'catégorie négative' => [['categorie_principale_id' => 0], 'categorie_principale_id'],
            'poids trop faible' => [['poids' => 0], 'poids'],
            'poids trop élevé' => [['poids' => 4], 'poids'],
            'poids non entier' => [['poids' => '2'], 'poids'],
            'repas non booléen' => [['convient_dejeuner' => 1], 'convient_dejeuner'],
            'aucun repas autorisé' => [['convient_dejeuner' => false, 'convient_diner' => false], 'repas'],
        ];
    }

    /**
     * @param array<string, mixed> $overrides une valeur null retire la clé du payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidPayload(array $overrides, string $expectedField): void
    {
        $result = $this->validate(DishFactory::payload($overrides));

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey($expectedField, $result->errors());
        $this->assertNotSame('', $result->errors()[$expectedField]);
    }

    public function testCollectsEveryErrorAtOnce(): void
    {
        $result = $this->validate(DishFactory::payload([
            'nom' => '  ',
            'saison' => 'ete',
            'poids' => 9,
        ]));

        $this->assertSame(['nom', 'saison', 'poids'], array_keys($result->errors()));
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function validate(array $payload): DishInputResult
    {
        return (new DishInputValidator(new DishNameNormalizer()))->validate($payload);
    }
}

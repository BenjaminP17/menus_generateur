<?php

namespace App\Tests\Menu;

use App\Menu\DishNameNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DishNameNormalizer::class)]
final class DishNameNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizationProvider(): array
    {
        return [
            'accent circonflexe' => ['Quîche', 'quiche'],
            'ligature oe majuscule' => ['Œufs', 'oeufs'],
            'ligature oe minuscule' => ['Bœuf bourguignon', 'boeuf bourguignon'],
            'cédille' => ['Ça mijote', 'ca mijote'],
            'tréma' => ['Paëlla', 'paella'],
            'majuscules et accents' => ['CRÈME brûlée', 'creme brulee'],
            'espaces en trop' => ['  Gratin   de   courgettes  ', 'gratin de courgettes'],
            'espace insécable' => ["Riz\u{00A0}cantonais", 'riz cantonais'],
            'apostrophe typographique' => ['Soupe à l’oignon', "soupe a l'oignon"],
            'séparateur conservé' => ['Riz / céréales', 'riz / cereales'],
            'déjà normalisé' => ['quiche', 'quiche'],
        ];
    }

    #[DataProvider('normalizationProvider')]
    public function testNormalizeBuildsTheUniquenessKey(string $name, string $expected): void
    {
        $this->assertSame($expected, (new DishNameNormalizer())->normalize($name));
    }

    public function testTwoSpellingsOfTheSameDishShareTheirKey(): void
    {
        $normalizer = new DishNameNormalizer();

        $this->assertSame(
            $normalizer->normalize('Œufs à la coque'),
            $normalizer->normalize('oeufs a la COQUE'),
        );
    }

    public function testCleanKeepsCaseAndAccents(): void
    {
        $this->assertSame(
            'Gratin de courgettes à la Crème',
            (new DishNameNormalizer())->clean("  Gratin  de   courgettes à la Crème \n"),
        );
    }

    /**
     * Les clés normalisées se trient dans l'ordre alphabétique français, ce que ne fait pas
     * la collation BINARY de SQLite sur la colonne nom.
     */
    public function testKeysSortInFrenchAlphabeticalOrder(): void
    {
        $normalizer = new DishNameNormalizer();
        $keys = array_map(
            static fn (string $name): string => $normalizer->normalize($name),
            ['Zucchini', 'Épinards', 'avocat', 'Œufs', 'Îlots flottants', 'banane'],
        );

        sort($keys);

        $this->assertSame(
            ['avocat', 'banane', 'epinards', 'ilots flottants', 'oeufs', 'zucchini'],
            $keys,
        );
    }
}

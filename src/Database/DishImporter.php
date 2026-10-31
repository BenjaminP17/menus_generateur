<?php

namespace App\Database;

use App\Menu\DishInputValidator;
use App\Menu\DishNameNormalizer;

/**
 * Ajoute en une fois une liste de plats saisie dans un fichier JSON (voir var/import/plats.json).
 *
 * Chaque plat passe par DishInputValidator et DishRepository, donc par les mêmes règles que la
 * saisie à la main. Le fichier désigne la catégorie par son libellé (« pâtes »), à la casse et
 * aux accents près. Un plat déjà en base est ignoré sans être modifié. Si un seul plat est
 * invalide, rien n'est écrit : l'import est tout ou rien.
 */
final class DishImporter
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly DishRepository $dishes,
        private readonly CategoryRepository $categories,
        private readonly DishInputValidator $validator,
        private readonly DishNameNormalizer $normalizer,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data   contenu du fichier : les plats sont sous la clé « plats », les clés qui commencent par « _ » sont de la documentation
     * @param bool                    $dryRun valide et compte sans rien écrire
     */
    public function import(array $data, bool $dryRun = false): DishImportReport
    {
        $items = $data['plats'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            return new DishImportReport([], [], ['Le fichier doit contenir une liste « plats ».']);
        }

        $categoryIds = [];
        foreach ($this->categories->findAll() as $category) {
            $categoryIds[$this->normalizer->normalize($category->label)] = $category->id;
        }

        /** @var list<\App\Menu\DishInput> $toCreate */
        $toCreate = [];
        /** @var list<string> $skipped */
        $skipped = [];
        /** @var list<string> $errors */
        $errors = [];
        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($items as $index => $item) {
            $position = sprintf('Plat n° %d', $index + 1);
            if (!is_array($item)) {
                $errors[] = $position.' : ce n\'est pas un objet.';

                continue;
            }

            $label = is_string($item['nom'] ?? null) && '' !== trim($item['nom']) ? sprintf('%s (« %s »)', $position, trim($item['nom'])) : $position;

            $category = $item['categorie'] ?? null;
            if (!is_string($category) || !isset($categoryIds[$this->normalizer->normalize($category)])) {
                $errors[] = sprintf('%s : catégorie inconnue ou absente.', $label);

                continue;
            }
            $item['categorie_principale_id'] = $categoryIds[$this->normalizer->normalize($category)];

            $result = $this->validator->validate($item);
            if (!$result->isValid()) {
                $errors[] = sprintf('%s : %s', $label, implode(' ', $result->errors()));

                continue;
            }

            $input = $result->input();
            $key = $this->normalizer->normalize($input->name);
            if (isset($seen[$key])) {
                $errors[] = sprintf('%s : ce nom apparaît plusieurs fois dans le fichier.', $label);

                continue;
            }
            $seen[$key] = true;

            if (null !== $this->dishes->findByName($input->name)) {
                $skipped[] = $input->name;

                continue;
            }

            $toCreate[] = $input;
        }

        $imported = array_map(static fn ($input): string => $input->name, $toCreate);
        if ([] !== $errors) {
            return new DishImportReport([], $skipped, $errors);
        }

        if (!$dryRun) {
            $this->pdo->beginTransaction();
            try {
                foreach ($toCreate as $input) {
                    $this->dishes->create($input);
                }
                $this->pdo->commit();
            } catch (\Throwable $error) {
                $this->pdo->rollBack();

                throw $error;
            }
        }

        return new DishImportReport($imported, $skipped, []);
    }
}

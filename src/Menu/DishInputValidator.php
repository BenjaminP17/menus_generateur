<?php

namespace App\Menu;

/**
 * Valide le corps JSON décodé d'une création ou d'une modification de plat (PRD §5.1).
 *
 * Classe PHP pure : aucune dépendance à PDO ni au framework, donc aucune des deux règles qui
 * exigent la base. L'existence de la catégorie principale et l'absence de doublon de nom sont
 * vérifiées par les dépôts, depuis le contrôleur.
 *
 * Toutes les erreurs sont collectées en un passage, à raison d'un message par champ, pour que
 * l'utilisateur corrige sa saisie d'un seul coup. Les clés inconnues du payload sont ignorées.
 */
final class DishInputValidator
{
    public const NAME_MAX_LENGTH = 120;

    private const DEFAULT_WEIGHT = 2;

    public function __construct(private readonly DishNameNormalizer $normalizer)
    {
    }

    /**
     * @param array<array-key, mixed> $data corps JSON décodé
     */
    public function validate(array $data): DishInputResult
    {
        /** @var array<string, string> $errors */
        $errors = [];

        $name = $this->validateName($data, $errors);
        $fitsLunch = $this->validateMeal($data, 'convient_dejeuner', $errors);
        $fitsDinner = $this->validateMeal($data, 'convient_diner', $errors);
        $season = $this->validateSeason($data, $errors);
        $type = $this->validateType($data, $errors);
        $level = $this->validateLevel($data, $errors);
        $categoryId = $this->validateCategoryId($data, $errors);
        $weight = $this->validateWeight($data, $errors);

        // Reprend la contrainte de table CHECK (convient_dejeuner = 1 OR convient_diner = 1).
        // Le champ « repas » est virtuel : il ne correspond à aucune clé du payload, mais le
        // frontend l'affiche à côté des deux cases à cocher.
        if (false === $fitsLunch && false === $fitsDinner) {
            $errors['repas'] = 'Le plat doit convenir au moins au déjeuner ou au dîner.';
        }

        if ([] !== $errors) {
            return DishInputResult::invalid($errors);
        }

        // Sans erreur, chaque champ a été validé : aucun n'est null.
        assert(null !== $name && null !== $fitsLunch && null !== $fitsDinner);
        assert(null !== $season && null !== $type && null !== $level);
        assert(null !== $categoryId && null !== $weight);

        return DishInputResult::valid(new DishInput(
            $name,
            $fitsLunch,
            $fitsDinner,
            $season,
            $type,
            $level,
            $categoryId,
            $weight,
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateName(array $data, array &$errors): ?string
    {
        if (!array_key_exists('nom', $data)) {
            $errors['nom'] = 'Le nom est obligatoire.';

            return null;
        }

        $value = $data['nom'];
        if (!is_string($value)) {
            $errors['nom'] = 'Le nom doit être un texte.';

            return null;
        }

        $name = $this->normalizer->clean($value);
        if ('' === $name) {
            $errors['nom'] = 'Le nom est obligatoire.';

            return null;
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors['nom'] = sprintf('Le nom ne doit pas dépasser %d caractères.', self::NAME_MAX_LENGTH);

            return null;
        }

        return $name;
    }

    /**
     * Un repas absent du payload est autorisé : le PRD §5.1 donne « les deux » par défaut.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateMeal(array $data, string $field, array &$errors): ?bool
    {
        if (!array_key_exists($field, $data)) {
            return true;
        }

        $value = $data[$field];
        if (!is_bool($value)) {
            $errors[$field] = 'Ce champ doit valoir vrai ou faux.';

            return null;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateSeason(array $data, array &$errors): ?Season
    {
        $value = $this->enumValue($data, 'saison', 'La saison est obligatoire.', $errors);
        if (null === $value) {
            return null;
        }

        $season = Season::tryFrom($value);
        if (null === $season) {
            $errors['saison'] = sprintf(
                'Saison inconnue. Valeurs attendues : %s.',
                implode(', ', Season::values()),
            );
        }

        return $season;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateType(array $data, array &$errors): ?DishType
    {
        $value = $this->enumValue($data, 'type', 'Le type est obligatoire.', $errors);
        if (null === $value) {
            return null;
        }

        $type = DishType::tryFrom($value);
        if (null === $type) {
            $errors['type'] = sprintf(
                'Type inconnu. Valeurs attendues : %s.',
                implode(', ', DishType::values()),
            );
        }

        return $type;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateLevel(array $data, array &$errors): ?Level
    {
        $value = $this->enumValue($data, 'niveau', 'Le niveau est obligatoire.', $errors);
        if (null === $value) {
            return null;
        }

        $level = Level::tryFrom($value);
        if (null === $level) {
            $errors['niveau'] = sprintf(
                'Niveau inconnu. Valeurs attendues : %s.',
                implode(', ', Level::values()),
            );
        }

        return $level;
    }

    /**
     * Lit un champ d'enum et renvoie sa valeur brute, ou null en consignant une erreur.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function enumValue(array $data, string $field, string $missingMessage, array &$errors): ?string
    {
        if (!array_key_exists($field, $data)) {
            $errors[$field] = $missingMessage;

            return null;
        }

        $value = $data[$field];
        if (!is_string($value)) {
            $errors[$field] = 'Ce champ doit être un texte.';

            return null;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateCategoryId(array $data, array &$errors): ?int
    {
        if (!array_key_exists('categorie_principale_id', $data)) {
            $errors['categorie_principale_id'] = 'La catégorie principale est obligatoire.';

            return null;
        }

        $value = $data['categorie_principale_id'];
        if (!is_int($value)) {
            $errors['categorie_principale_id'] = 'La catégorie principale doit être un entier.';

            return null;
        }

        if ($value < 1) {
            $errors['categorie_principale_id'] = 'Catégorie principale inconnue.';

            return null;
        }

        return $value;
    }

    /**
     * Le poids est facultatif : le PRD §5.1 donne « normal » par défaut.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateWeight(array $data, array &$errors): ?int
    {
        if (!array_key_exists('poids', $data)) {
            return self::DEFAULT_WEIGHT;
        }

        $value = $data['poids'];
        if (!is_int($value) || $value < 1 || $value > 3) {
            $errors['poids'] = 'Le poids doit valoir 1 (rarement), 2 (normal) ou 3 (favori).';

            return null;
        }

        return $value;
    }
}

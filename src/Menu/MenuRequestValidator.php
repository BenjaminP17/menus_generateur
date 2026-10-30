<?php

namespace App\Menu;

/**
 * Valide le corps JSON décodé d'une demande de menu (PRD §5.2).
 *
 * Les jours de déjeuner et de dîner sont obligatoires (au moins un jour chacun, sans doublon) ; la proportion végétarienne (« quelques-uns ») et la date
 * de référence (date du jour) ont une valeur par défaut. Toutes les erreurs sont collectées en un
 * passage, à raison d'un message par champ. Les clés inconnues du payload sont ignorées.
 */
final class MenuRequestValidator
{
    public function __construct(private readonly Clock $clock)
    {
    }

    /**
     * @param array<array-key, mixed> $data corps JSON décodé
     */
    public function validate(array $data): MenuRequestResult
    {
        /** @var array<string, string> $errors */
        $errors = [];

        $lunchDays = $this->validateDays($data, 'jours_dejeuners', $errors);
        $dinnerDays = $this->validateDays($data, 'jours_diners', $errors);
        $share = $this->validateShare($data, $errors);
        $date = $this->validateDate($data, $errors);

        if ([] !== $errors) {
            return MenuRequestResult::invalid($errors);
        }

        // Sans erreur, chaque champ a été validé : aucun n'est null.
        assert(null !== $lunchDays && null !== $dinnerDays && null !== $share && null !== $date);

        return MenuRequestResult::valid(new MenuRequest($lunchDays, $dinnerDays, $share, $date));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     *
     * @return list<int>|null index des jours, triés
     */
    private function validateDays(array $data, string $field, array &$errors): ?array
    {
        if (!array_key_exists($field, $data)) {
            $errors[$field] = 'Ce champ est obligatoire.';

            return null;
        }

        $value = $data[$field];
        if (!is_array($value) || !array_is_list($value)) {
            $errors[$field] = 'Ce champ doit être une liste de jours.';

            return null;
        }

        if ([] === $value) {
            $errors[$field] = 'Choisis au moins un jour.';

            return null;
        }

        $days = [];
        foreach ($value as $name) {
            $day = is_string($name) ? Weekday::tryFrom($name) : null;
            if (null === $day) {
                $errors[$field] = sprintf('Jour inconnu. Valeurs attendues : %s.', implode(', ', Weekday::values()));

                return null;
            }

            if (in_array($day->index(), $days, true)) {
                $errors[$field] = sprintf('Le jour « %s » est indiqué plusieurs fois.', $day->value);

                return null;
            }

            $days[] = $day->index();
        }

        sort($days);

        return $days;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateShare(array $data, array &$errors): ?VegetarianShare
    {
        if (!array_key_exists('vegetarien', $data) || null === $data['vegetarien']) {
            return VegetarianShare::Some;
        }

        $value = $data['vegetarien'];
        if (!is_string($value)) {
            $errors['vegetarien'] = 'Ce champ doit être un texte.';

            return null;
        }

        $share = VegetarianShare::tryFrom($value);
        if (null === $share) {
            $errors['vegetarien'] = sprintf(
                'Proportion inconnue. Valeurs attendues : %s.',
                implode(', ', VegetarianShare::values()),
            );
        }

        return $share;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateDate(array $data, array &$errors): ?\DateTimeImmutable
    {
        if (!array_key_exists('date_reference', $data) || null === $data['date_reference']) {
            return $this->clock->now();
        }

        $value = $data['date_reference'];
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        // createFromFormat accepte « 2026-02-31 » en le reportant au 3 mars : on exige l'aller-retour.
        if (false === $date || $date->format('Y-m-d') !== $value) {
            $errors['date_reference'] = 'La date doit être au format AAAA-MM-JJ.';

            return null;
        }

        return $date;
    }
}

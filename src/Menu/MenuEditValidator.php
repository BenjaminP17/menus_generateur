<?php

namespace App\Menu;

/**
 * Valide les champs d'une modification de menu, en plus des réglages (MenuRequestValidator) :
 * `repas` (le menu en cours), `cible` (le repas à changer) et `plat_id` (le plat choisi à la
 * main).
 *
 * Les repas doivent tomber aux jours choisis dans les réglages, sans doublon ; un repas peut
 * manquer au menu en cours (base insuffisante). Comme ailleurs, une erreur par champ, et les
 * clés inconnues sont ignorées.
 */
final class MenuEditValidator
{
    /**
     * @param array<array-key, mixed> $data corps JSON décodé
     */
    public function validate(array $data, MenuRequest $request, bool $targetRequired, bool $dishRequired): MenuEditResult
    {
        /** @var array<string, string> $errors */
        $errors = [];

        $placements = $this->validatePlacements($data, $request, $errors);
        $target = $this->validateTarget($data, $request, $targetRequired, $errors);
        $dishId = $this->validateDishId($data, $dishRequired, $errors);

        if ([] !== $errors) {
            return MenuEditResult::invalid($errors);
        }

        assert(null !== $placements);

        return MenuEditResult::valid(new MenuEdit($placements, $target[0] ?? null, $target[1] ?? null, $dishId));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     *
     * @return list<MenuPlacement>|null
     */
    private function validatePlacements(array $data, MenuRequest $request, array &$errors): ?array
    {
        $value = $data['repas'] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            $errors['repas'] = 'Ce champ doit être la liste des repas du menu.';

            return null;
        }

        $placements = [];
        $seen = [];
        foreach ($value as $item) {
            $slot = is_array($item) ? $this->readSlot($item, $request) : null;
            $dishId = is_array($item) ? $this->readId($item['plat_id'] ?? null) : null;
            if (null === $slot || null === $dishId) {
                $errors['repas'] = 'Chaque repas doit indiquer un jour, un repas et un plat_id, aux jours choisis.';

                return null;
            }

            $key = $slot[0].$slot[1]->value;
            if (isset($seen[$key])) {
                $errors['repas'] = 'Un même repas est indiqué plusieurs fois.';

                return null;
            }
            $seen[$key] = true;

            $placements[] = new MenuPlacement($slot[0], $slot[1], $dishId);
        }

        return $placements;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     *
     * @return array{int, MealKind}|null
     */
    private function validateTarget(array $data, MenuRequest $request, bool $required, array &$errors): ?array
    {
        if (!array_key_exists('cible', $data) || null === $data['cible']) {
            if ($required) {
                $errors['cible'] = 'Ce champ est obligatoire.';
            }

            return null;
        }

        $slot = is_array($data['cible']) ? $this->readSlot($data['cible'], $request) : null;
        if (null === $slot) {
            $errors['cible'] = 'La cible doit indiquer un jour et un repas, aux jours choisis.';
        }

        return $slot;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $errors
     */
    private function validateDishId(array $data, bool $required, array &$errors): ?int
    {
        if (!array_key_exists('plat_id', $data) || null === $data['plat_id']) {
            if ($required) {
                $errors['plat_id'] = 'Ce champ est obligatoire.';
            }

            return null;
        }

        $id = $this->readId($data['plat_id']);
        if (null === $id) {
            $errors['plat_id'] = 'Ce champ doit être un identifiant de plat.';
        }

        return $id;
    }

    /**
     * @param array<array-key, mixed> $item
     *
     * @return array{int, MealKind}|null le jour (index) et le repas, s'ils sont connus et choisis
     */
    private function readSlot(array $item, MenuRequest $request): ?array
    {
        $day = is_string($item['jour'] ?? null) ? Weekday::tryFrom($item['jour']) : null;
        $kind = is_string($item['repas'] ?? null) ? MealKind::tryFrom($item['repas']) : null;
        if (null === $day || null === $kind || !$request->hasSlot($day->index(), $kind)) {
            return null;
        }

        return [$day->index(), $kind];
    }

    private function readId(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }
}

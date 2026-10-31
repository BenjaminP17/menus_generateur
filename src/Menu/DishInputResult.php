<?php

namespace App\Menu;

/**
 * Résultat de la validation d'un plat : soit une entrée exploitable, soit des erreurs par champ.
 *
 * Une saisie invalide est un cas de figure attendu, pas une exception : le contrôleur traite les
 * deux branches, et input() a un type de retour non nullable.
 */
final class DishInputResult
{
    /**
     * @param array<string, string> $errors un message par champ, la clé est le nom du champ JSON
     */
    private function __construct(
        private readonly ?DishInput $input,
        private readonly array $errors,
    ) {
    }

    public static function valid(DishInput $input): self
    {
        return new self($input, []);
    }

    /**
     * @param array<string, string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(null, $errors);
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function input(): DishInput
    {
        return $this->input ?? throw new \LogicException('Entrée invalide : lire errors() avant input().');
    }
}

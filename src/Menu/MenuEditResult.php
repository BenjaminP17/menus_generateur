<?php

namespace App\Menu;

/**
 * Résultat de la validation d'une modification de menu : soit une modification exploitable,
 * soit des erreurs par champ (même principe que MenuRequestResult).
 */
final class MenuEditResult
{
    /**
     * @param array<string, string> $errors un message par champ, la clé est le nom du champ JSON
     */
    private function __construct(
        private readonly ?MenuEdit $edit,
        private readonly array $errors,
    ) {
    }

    public static function valid(MenuEdit $edit): self
    {
        return new self($edit, []);
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

    public function edit(): MenuEdit
    {
        return $this->edit ?? throw new \LogicException('Modification invalide : lire errors() avant edit().');
    }
}

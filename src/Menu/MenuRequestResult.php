<?php

namespace App\Menu;

/**
 * Résultat de la validation des réglages d'un menu : soit une demande exploitable, soit des
 * erreurs par champ (même principe que DishInputResult).
 */
final class MenuRequestResult
{
    /**
     * @param array<string, string> $errors un message par champ, la clé est le nom du champ JSON
     */
    private function __construct(
        private readonly ?MenuRequest $request,
        private readonly array $errors,
    ) {
    }

    public static function valid(MenuRequest $request): self
    {
        return new self($request, []);
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

    public function request(): MenuRequest
    {
        return $this->request ?? throw new \LogicException('Demande invalide : lire errors() avant request().');
    }
}

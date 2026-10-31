<?php

namespace App\Database;

/**
 * Bilan d'un import de plats : ce qui est (ou serait, en simulation) ajouté, ce qui existait déjà
 * et les erreurs qui ont empêché l'import.
 */
final class DishImportReport
{
    /**
     * @param list<string> $imported noms des plats ajoutés
     * @param list<string> $skipped  noms des plats déjà en base, laissés tels quels
     * @param list<string> $errors   une ligne par plat refusé ; non vide, rien n'est écrit
     */
    public function __construct(
        public readonly array $imported,
        public readonly array $skipped,
        public readonly array $errors,
    ) {
    }

    public function isSuccessful(): bool
    {
        return [] === $this->errors;
    }
}

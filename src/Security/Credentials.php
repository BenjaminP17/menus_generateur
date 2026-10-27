<?php

namespace App\Security;

/**
 * Vérifie les identifiants du compte unique (jalon 1b, SEC-01). Un seul foyer, un seul compte :
 * pas de table utilisateur, les valeurs viennent de .env.local (jamais commité), voir
 * config/services.yaml.
 */
final class Credentials
{
    public function __construct(
        private readonly string $identifiant,
        private readonly string $passwordHash,
    ) {
    }

    public function verify(string $identifiant, string $motDePasse): bool
    {
        if ('' === $this->identifiant || !hash_equals($this->identifiant, $identifiant)) {
            return false;
        }

        return password_verify($motDePasse, $this->passwordHash);
    }
}

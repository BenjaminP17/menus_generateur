<?php

namespace App\Menu;

/**
 * Facteur de fraîcheur du tirage (PRD §5.4) : un plat proposé récemment est moins souvent tiré,
 * un plat jamais proposé est favorisé.
 *
 * Classe pure : elle reçoit la date de dernière proposition de chaque plat (lue dans le journal
 * `proposition` par le dépôt) et ne touche pas à la base. Le poids du tirage devient `poids`
 * multiplié par un pourcentage :
 *
 * - plat jamais proposé, ou dont la dernière proposition a plus de RETENTION_WEEKS semaines (le
 *   journal l'a déjà purgé) : NEVER_PERCENT ;
 * - sinon le pourcentage remonte linéairement avec le nombre de semaines écoulées, de
 *   NEVER_PERCENT / RETENTION_WEEKS (la semaine d'avant) jusqu'à NEVER_PERCENT.
 *
 * Seuls ces deux réglages fixent la force de la pénalité (ROADMAP, « Questions à trancher »).
 * Sans historique, le poids reste exactement `poids` : le tirage est celui du jalon 3.
 */
final class Freshness
{
    /** Durée de rétention du journal, en semaines. */
    public const RETENTION_WEEKS = 8;

    /** Pourcentage du poids d'un plat jamais proposé. */
    private const NEVER_PERCENT = 150;

    /**
     * @param array<int, int> $weeksSince semaines écoulées depuis la dernière proposition, par identifiant de plat
     */
    private function __construct(private readonly array $weeksSince)
    {
    }

    /**
     * Aucun historique : le poids de chaque plat est inchangé.
     */
    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<int, \DateTimeImmutable> $lastProposed dernier lundi de menu où chaque plat a été proposé
     * @param \DateTimeImmutable             $weekStart    lundi de la semaine du menu composé
     */
    public static function fromHistory(array $lastProposed, \DateTimeImmutable $weekStart): self
    {
        $weeks = [];
        foreach ($lastProposed as $dishId => $date) {
            // Une semaine d'écart au moins : la semaine du menu lui-même n'est jamais comptée,
            // et une semaine future compte comme une semaine passée de même écart.
            $weeks[$dishId] = max(1, (int) round((int) $weekStart->diff($date)->days / 7));
        }

        return new self($weeks);
    }

    /**
     * Poids de tirage du plat : son poids de préférence, modulé par sa fraîcheur.
     *
     * @return positive-int
     */
    public function weight(Dish $dish): int
    {
        if ([] === $this->weeksSince) {
            return $dish->weight;
        }

        $weeks = $this->weeksSince[$dish->id] ?? null;
        $percent = null === $weeks || $weeks >= self::RETENTION_WEEKS
            ? self::NEVER_PERCENT
            : max(1, (int) round(self::NEVER_PERCENT * $weeks / self::RETENTION_WEEKS));

        return $dish->weight * $percent;
    }
}

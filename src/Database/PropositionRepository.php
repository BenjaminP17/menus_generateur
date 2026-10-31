<?php

namespace App\Database;

use App\Menu\Clock;
use App\Menu\Freshness;
use App\Menu\MenuPlacement;
use App\Menu\Week;

/**
 * Accès au journal des propositions (table proposition, voir doc/modelisation-bdd.md).
 *
 * Le journal ne garde ni menu ni recette : une ligne par plat d'un menu validé, à un jour et un
 * repas donnés, repérée par le lundi de sa semaine. Il sert à la règle de fraîcheur du tirage.
 */
final class PropositionRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Enregistre le menu validé d'une semaine : les lignes déjà gardées pour cette semaine sont
     * remplacées, pour que le journal corresponde toujours au dernier menu validé. Les lignes de
     * plus de Freshness::RETENTION_WEEKS semaines sont purgées dans la même transaction.
     *
     * @param list<MenuPlacement> $placements les plats doivent exister (clé étrangère)
     */
    public function replaceWeek(\DateTimeImmutable $monday, array $placements): void
    {
        $this->pdo->beginTransaction();

        try {
            $date = $monday->format('Y-m-d');
            $this->pdo->prepare('DELETE FROM proposition WHERE date_menu = :date_menu')
                ->execute(['date_menu' => $date]);

            $insert = $this->pdo->prepare(
                'INSERT INTO proposition (plat_id, date_menu, jour, repas, origine, cree_le) '
                .'VALUES (:plat_id, :date_menu, :jour, :repas, :origine, :cree_le)',
            );
            $now = $this->clock->now()->format(\DateTimeInterface::ATOM);
            foreach ($placements as $placement) {
                $insert->execute([
                    'plat_id' => $placement->dishId,
                    'date_menu' => $date,
                    // La base compte les jours de 1 (lundi) à 7 ; le menu, de 0 à 6.
                    'jour' => $placement->day + 1,
                    'repas' => $placement->kind->value,
                    'origine' => $placement->origin->value,
                    'cree_le' => $now,
                ]);
            }

            $this->purge();
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();

            throw $error;
        }
    }

    /**
     * Dernière semaine où chaque plat a été proposé, hors la semaine donnée : celle-ci sera
     * remplacée à la prochaine validation, elle ne doit pas peser sur le tirage.
     *
     * @return array<int, \DateTimeImmutable> lundi de la dernière semaine, par identifiant de plat
     */
    public function lastProposedByDish(\DateTimeImmutable $excludedMonday): array
    {
        $statement = $this->pdo->prepare(
            'SELECT plat_id, MAX(date_menu) AS derniere FROM proposition '
            .'WHERE date_menu <> :date_menu GROUP BY plat_id',
        );
        $statement->execute(['date_menu' => $excludedMonday->format('Y-m-d')]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $last = [];
        foreach ($rows as $row) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row['derniere']);
            if (false !== $date) {
                $last[(int) $row['plat_id']] = $date;
            }
        }

        return $last;
    }

    /**
     * Supprime les lignes de plus de Freshness::RETENTION_WEEKS semaines, comptées depuis la
     * semaine en cours.
     *
     * @return int nombre de lignes supprimées
     */
    public function purge(): int
    {
        $limit = Week::mondayOf($this->clock->now())
            ->modify(sprintf('-%d weeks', Freshness::RETENTION_WEEKS));

        $statement = $this->pdo->prepare('DELETE FROM proposition WHERE date_menu < :limite');
        $statement->execute(['limite' => $limit->format('Y-m-d')]);

        return $statement->rowCount();
    }

    /**
     * Vide le journal : les plats et les catégories ne sont pas touchés.
     *
     * @return int nombre de lignes supprimées
     */
    public function clear(): int
    {
        return (int) $this->pdo->exec('DELETE FROM proposition');
    }
}

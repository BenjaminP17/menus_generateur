<?php

namespace App\Database;

use App\Menu\Clock;
use App\Menu\Dish;
use App\Menu\DishInput;
use App\Menu\DishNameNormalizer;
use App\Menu\DishType;
use App\Menu\Level;
use App\Menu\Season;

/**
 * Accès à la table plat (PRD §5.1).
 *
 * Reçoit la connexion PDO, et non App\Database\Database, pour que les tests puissent travailler
 * sur une base en mémoire (voir tests/Support/InMemoryDatabase.php).
 */
final class DishRepository
{
    private const COLUMNS = 'id, nom, nom_normalise, convient_dejeuner, convient_diner, saison, type, '
        .'niveau, categorie_principale_id, poids, cree_le, modifie_le';

    public function __construct(
        private readonly \PDO $pdo,
        private readonly DishNameNormalizer $normalizer,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Tous les plats, triés par nom sans tenir compte des accents ni de la casse.
     *
     * Le tri porte sur nom_normalise : ORDER BY nom utiliserait la collation BINARY de SQLite,
     * qui classe « Épinards » et « Œufs » après « Zucchini ».
     *
     * @return list<Dish>
     */
    public function findAll(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT '.self::COLUMNS.' FROM plat ORDER BY nom_normalise')->fetchAll();

        return array_map(fn (array $row): Dish => $this->hydrate($row), $rows);
    }

    public function find(int $id): ?Dish
    {
        return $this->fetchOne('id = :id', ['id' => $id]);
    }

    /**
     * Recherche par nom, à la normalisation près : « Quîche » trouve « Quiche ».
     */
    public function findByName(string $name): ?Dish
    {
        return $this->fetchOne(
            'nom_normalise = :nom_normalise',
            ['nom_normalise' => $this->normalizer->normalize($name)],
        );
    }

    /**
     * @throws DuplicateDishNameException si un autre plat porte déjà ce nom
     */
    public function create(DishInput $input): Dish
    {
        $this->assertNameIsFree($input->name);

        $now = $this->timestamp();
        $values = $this->values($input);
        $values['cree_le'] = $now;
        $values['modifie_le'] = $now;

        $columns = array_keys($values);
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO plat (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $column): string => ':'.$column, $columns)),
        ));

        try {
            $statement->execute($values);
        } catch (\PDOException $error) {
            throw $this->duplicateOr($error, $input->name);
        }

        return $this->find((int) $this->pdo->lastInsertId())
            ?? throw new \RuntimeException('Le plat créé est introuvable.');
    }

    /**
     * Conserve cree_le et n'avance que modifie_le.
     *
     * @return Dish|null null si aucun plat ne porte cet identifiant
     *
     * @throws DuplicateDishNameException si le nom appartient à un autre plat
     */
    public function update(int $id, DishInput $input): ?Dish
    {
        if (null === $this->find($id)) {
            return null;
        }

        $this->assertNameIsFree($input->name, $id);

        $values = $this->values($input);
        $values['modifie_le'] = $this->timestamp();

        $assignments = implode(', ', array_map(
            static fn (string $column): string => $column.' = :'.$column,
            array_keys($values),
        ));

        $statement = $this->pdo->prepare('UPDATE plat SET '.$assignments.' WHERE id = :id');

        try {
            $statement->execute([...$values, 'id' => $id]);
        } catch (\PDOException $error) {
            throw $this->duplicateOr($error, $input->name);
        }

        return $this->find($id);
    }

    /**
     * Supprime sans condition (PRD §5.1) : l'historique du plat dans proposition part en cascade.
     *
     * @return bool false si aucun plat ne portait cet identifiant
     */
    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM plat WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * Lit au plus un plat.
     *
     * @param array<string, int|string> $parameters
     */
    private function fetchOne(string $where, array $parameters): ?Dish
    {
        $statement = $this->pdo->prepare('SELECT '.self::COLUMNS.' FROM plat WHERE '.$where);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Colonnes communes à l'insertion et à la modification.
     *
     * Les booléens sont convertis en entiers : PDO lierait false en chaîne vide, que l'affinité
     * INTEGER de SQLite ne convertit pas, et le CHECK (convient_dejeuner IN (0, 1)) échouerait.
     *
     * @return array<string, int|string>
     */
    private function values(DishInput $input): array
    {
        return [
            'nom' => $input->name,
            'nom_normalise' => $this->normalizer->normalize($input->name),
            'convient_dejeuner' => (int) $input->fitsLunch,
            'convient_diner' => (int) $input->fitsDinner,
            'saison' => $input->season->value,
            'type' => $input->type->value,
            'niveau' => $input->level->value,
            'categorie_principale_id' => $input->categoryId,
            'poids' => $input->weight,
        ];
    }

    private function timestamp(): string
    {
        return $this->clock->now()->format(\DateTimeInterface::ATOM);
    }

    /**
     * @throws DuplicateDishNameException
     */
    private function assertNameIsFree(string $name, ?int $exceptId = null): void
    {
        $existing = $this->findByName($name);
        if (null !== $existing && $existing->id !== $exceptId) {
            throw new DuplicateDishNameException($existing);
        }
    }

    /**
     * Filet de sécurité : si une écriture concurrente se glissait entre la vérification et
     * l'insertion, la violation d'unicité est traduite comme un doublon plutôt qu'en erreur
     * serveur.
     *
     * SQLite renvoie le même SQLSTATE 23000 pour UNIQUE, CHECK, NOT NULL et les clés étrangères :
     * seul le message distingue l'unicité du nom.
     */
    private function duplicateOr(\PDOException $error, string $name): \Throwable
    {
        $isDuplicate = '23000' === ($error->errorInfo[0] ?? null)
            && str_contains($error->getMessage(), 'plat.nom_normalise');

        if (!$isDuplicate) {
            return $error;
        }

        $existing = $this->findByName($name);

        return null === $existing ? $error : new DuplicateDishNameException($existing);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Dish
    {
        // from() et non tryFrom() : les CHECK garantissent les valeurs, donc une valeur hors
        // liste signale une base corrompue et doit échouer bruyamment.
        return new Dish(
            (int) $row['id'],
            (string) $row['nom'],
            (string) $row['nom_normalise'],
            1 === (int) $row['convient_dejeuner'],
            1 === (int) $row['convient_diner'],
            Season::from((string) $row['saison']),
            DishType::from((string) $row['type']),
            Level::from((string) $row['niveau']),
            (int) $row['categorie_principale_id'],
            (int) $row['poids'],
            new \DateTimeImmutable((string) $row['cree_le']),
            new \DateTimeImmutable((string) $row['modifie_le']),
        );
    }
}

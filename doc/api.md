# API JSON

Contrat de l'API de `menus_generator`. Les règles métier sont dans [PRD.md](../PRD.md), le modèle
de données dans [modelisation-bdd.md](modelisation-bdd.md).

Conventions : les chemins, les champs des payloads et les messages sont en français, alignés sur
les colonnes de la base. Les identifiants de code PHP restent en anglais. Toutes les requêtes et
réponses portent du JSON (`Content-Type: application/json`), en même origine, sans authentification
(un seul foyer, pas de compte).

## Endpoints

| Méthode | Chemin | Description |
|---|---|---|
| GET | `/api/health` | Vérifie que l'API répond et que la base est accessible |
| GET | `/api/categories` | Liste les 8 catégories principales (PRD §5.1) |
| GET | `/api/plats` | Liste tous les plats, triés par nom |
| POST | `/api/plats` | Crée un plat |
| GET | `/api/plats/{id}` | Lit un plat |
| PUT | `/api/plats/{id}` | Modifie un plat (remplacement complet) |
| DELETE | `/api/plats/{id}` | Supprime un plat |

`{id}` doit être un entier : un identifiant non numérique est refusé par le routeur (404).

## Le plat

### En entrée (POST et PUT)

| Champ | Type | Obligatoire | Règle |
|---|---|---|---|
| `nom` | texte | oui | Non vide, 120 caractères au plus. Unique à la casse et aux accents près |
| `convient_dejeuner` | booléen | non (défaut `true`) | Vrais booléens JSON uniquement |
| `convient_diner` | booléen | non (défaut `true`) | Au moins l'un des deux doit valoir `true` |
| `saison` | texte | oui | `printemps_ete`, `automne_hiver` ou `hybride` |
| `type` | texte | oui | `classique` ou `vegetarien` |
| `niveau` | texte | oui | `rapide` ou `elabore` |
| `categorie_principale_id` | entier | oui | Doit exister dans `/api/categories` |
| `poids` | entier | non (défaut `2`) | 1 (rarement), 2 (normal) ou 3 (favori) |

Les clés inconnues sont ignorées, `id` compris : l'identifiant vient toujours de l'URL.

```json
{
  "nom": "Gratin de courgettes",
  "convient_dejeuner": true,
  "convient_diner": true,
  "saison": "printemps_ete",
  "type": "vegetarien",
  "niveau": "rapide",
  "categorie_principale_id": 6,
  "poids": 2
}
```

### En sortie

```json
{
  "id": 12,
  "nom": "Gratin de courgettes",
  "convient_dejeuner": true,
  "convient_diner": true,
  "saison": "printemps_ete",
  "type": "vegetarien",
  "niveau": "rapide",
  "categorie_principale_id": 6,
  "poids": 2,
  "cree_le": "2026-10-26T09:15:59+00:00",
  "modifie_le": "2026-10-26T09:16:00+00:00"
}
```

Les dates sont en ISO 8601, toujours en UTC. La colonne `nom_normalise` n'est pas exposée : c'est
une clé technique interne qui garantit l'unicité du nom.

### Collections

Les collections sont enveloppées, pour laisser la place à des métadonnées plus tard :

```json
{ "plats": [ { "id": 12, "nom": "Gratin de courgettes", "…": "…" } ] }
{ "categories": [ { "id": 1, "libelle": "pâtes" } ] }
```

`GET /api/plats` renvoie la base entière, sans pagination ni filtre : le volume reste de quelques
centaines de plats (PRD §7) et le tri ignore la casse et les accents.

## Erreurs

Toutes les erreurs métier ont la même forme. `erreur` est toujours présent et peut être affiché
tel quel. `champs` n'apparaît que pour les erreurs rattachées à un champ, et ses clés sont les noms
des champs du payload.

```json
{
  "erreur": "Les données envoyées sont invalides.",
  "champs": {
    "nom": "Le nom est obligatoire.",
    "poids": "Le poids doit valoir 1 (rarement), 2 (normal) ou 3 (favori)."
  }
}
```

La clé `repas` est un champ virtuel : elle ne correspond à aucun champ du payload et signale que
ni le déjeuner ni le dîner n'est autorisé. L'interface l'affiche à côté des deux cases à cocher.

| Cas | Code |
|---|---|
| Lecture, liste, modification | 200 |
| Création | 201, avec l'en-tête `Location` |
| Suppression | 204, corps vide |
| Corps JSON illisible, vide, ou racine qui n'est pas un objet | 400 |
| Plat introuvable | 404 |
| Un plat porte déjà ce nom | 409, avec `champs.nom` |
| Validation en échec, ou catégorie principale inconnue | 422, avec `champs` |

Deux limites connues :

- Les erreurs qui n'atteignent pas l'application (URL inconnue, identifiant non numérique) sont
  rendues par Symfony au format RFC 7807 (`{"title","status","detail"}`), pas au format ci-dessus.
  Le frontend n'émet pas de telles requêtes ; l'unification est un candidat pour le jalon 6.
- Les réponses échappent les caractères non ASCII (`Pâtes`). C'est du JSON valide, transparent
  pour `JSON.parse`.

## Suppression d'un plat

La suppression est libre et sans condition (PRD §5.1) : aucune vérification d'usage. L'historique
du plat dans `proposition` est effacé en cascade, et un menu déjà affiché dans le navigateur n'est
pas modifié, puisqu'aucun menu n'est stocké côté serveur.

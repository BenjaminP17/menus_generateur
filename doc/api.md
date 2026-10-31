# API JSON

Contrat de l'API de `menus_generator`. Les règles métier sont dans [PRD.md](../PRD.md), le modèle
de données dans [modelisation-bdd.md](modelisation-bdd.md).

Conventions : les chemins, les champs des payloads et les messages sont en français, alignés sur
les colonnes de la base. Les identifiants de code PHP restent en anglais. Toutes les requêtes et
réponses portent du JSON (`Content-Type: application/json`), en même origine.

Authentification (jalon 1b, SEC-01) : un compte unique protège l'API, par session (cookie
`SameSite=Strict`). Toute route `/api/*` renvoie 401 sans session active, sauf `/api/health` et
`/api/session` elle-même. Pas de table `utilisateur` : un seul foyer, identifiants dans
`.env.local` (voir CLAUDE.md).

## Endpoints

| Méthode | Chemin | Description |
|---|---|---|
| GET | `/api/health` | Vérifie que l'API répond et que la base est accessible |
| GET | `/api/session` | État de la connexion (`{"connecte": true\|false}`), accessible sans session |
| POST | `/api/session` | Connexion (`{identifiant, mot_de_passe}`) ; 429 au-delà de 5 tentatives / 15 min |
| DELETE | `/api/session` | Déconnexion |
| GET | `/api/categories` | Liste les 8 catégories principales (PRD §5.1) |
| GET | `/api/plats` | Liste tous les plats, triés par nom |
| POST | `/api/plats` | Crée un plat |
| GET | `/api/plats/{id}` | Lit un plat |
| PUT | `/api/plats/{id}` | Modifie un plat (remplacement complet) |
| DELETE | `/api/plats/{id}` | Supprime un plat |
| POST | `/api/menu` | Génère un menu |
| POST | `/api/menu/regenerer` | Change un repas du menu (ou tout le menu, sans `cible`) |
| POST | `/api/menu/choisir` | Pose un plat choisi à la main sur un repas |

`{id}` doit être un entier d'au plus 9 chiffres : un identifiant non numérique ou hors bornes est
refusé par le routeur (404), proprement, sans révéler de détail serveur (SEC-02).

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

## Le menu

Le serveur ne stocke aucun menu (ROADMAP, jalon 4) : le navigateur garde le menu en cours et le
renvoie à chaque demande de modification. Les trois routes reçoivent les **réglages** et renvoient
le **menu** complet.

### Réglages (en entrée, les trois routes)

| Champ | Type | Obligatoire | Règle |
|---|---|---|---|
| `jours_dejeuners` | liste de textes | oui | 1 à 7 jours distincts parmi `lundi` … `dimanche` |
| `jours_diners` | liste de textes | oui | Idem, indépendante des déjeuners |
| `vegetarien` | texte | non (défaut `quelques_uns`) | `aucun`, `quelques_uns` ou `majoritairement` |
| `date_reference` | texte `AAAA-MM-JJ` | non (défaut : aujourd'hui) | Détermine la saison |

Le menu contient exactement un déjeuner à chaque jour de déjeuner et un dîner à chaque jour de dîner.

### Menu en cours (en entrée de `/regenerer` et `/choisir`)

| Champ | Type | Règle |
|---|---|---|
| `repas` | liste d'objets | Les repas du menu en cours : `{"jour": "lundi", "repas": "dejeuner", "plat_id": 12}`. Optionnel (défaut : liste vide). Chaque repas doit tomber à un jour choisi dans les réglages, sans doublon. Un repas peut manquer (base insuffisante). `repas` vaut `dejeuner` ou `diner` |
| `cible` | objet | Le repas à changer : `{"jour": "lundi", "repas": "dejeuner"}`. Obligatoire pour `/choisir`, optionnel pour `/regenerer` |
| `plat_id` | entier | Le plat choisi. Obligatoire pour `/choisir`, ignoré ailleurs |

Un plat de `repas` supprimé entre-temps (PRD §5.1) n'est pas une erreur : le repas est traité comme
absent, et un avertissement `plat_manquant` le signale dans le menu renvoyé.

### Menu (en sortie)

```json
{
  "menu": {
    "repas": [
      { "jour": "lundi", "repas": "dejeuner", "plat": { "id": 12, "nom": "Gratin de courgettes", "…": "…" } }
    ],
    "avertissements": [
      { "type": "niveau_de_repli", "message": "Le déjeuner du samedi est un plat rapide, alors qu'un plat élaboré est attendu ce jour-là.", "jour": "samedi", "repas": "dejeuner" },
      { "type": "vegetarien_insuffisant", "message": "Le menu compte 3 repas végétariens sur 14, au lieu de 8 au moins." }
    ],
    "complet": true
  }
}
```

- `repas` est trié par jour, déjeuner avant dîner ; `plat` a la forme d'un plat (voir plus haut).
- `complet` vaut `false` si au moins un repas n'a pas pu être composé (avertissement `plat_manquant`).
- `jour` et `repas` n'apparaissent dans un avertissement que s'il concerne un repas précis.
- Les messages sont destinés à l'utilisateur et affichables tels quels.

Types d'avertissement : `plat_manquant`, `niveau_de_repli` (le niveau du jour, rapide en semaine et
élaboré le week-end, n'est pas respecté), `vegetarien_insuffisant`, `vegetarien_excessif`,
`plat_en_double`, `hors_saison`, `repas_non_autorise`. Le générateur ne produit que les trois
premiers ; les autres ne viennent que d'un choix libre. Les avertissements sont recalculés sur le
menu entier à chaque réponse.

### Les routes

- `POST /api/menu` : compose un menu à partir des réglages. Les corps de `repas`, `cible` et `plat_id`
  sont ignorés. Avec une base vide, la réponse est un 200 sans repas, avec un `plat_manquant` par repas.
- `POST /api/menu/regenerer` : avec `cible`, tire un autre plat pour ce repas, qui respecte les règles
  vis-à-vis des autres repas du menu, et laisse tous les autres repas tels quels (409 s'il n'existe
  aucun autre plat qui convienne). Sans `cible`, compose un nouveau menu complet.
- `POST /api/menu/choisir` : pose `plat_id` sur `cible`. Le choix est libre : une règle non respectée
  donne un avertissement, jamais un refus. 422 avec `champs.plat_id` si le plat n'existe pas.

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
| Création, connexion réussie | 201 (avec l'en-tête `Location`) ou 204 |
| Suppression, déconnexion | 204, corps vide |
| Corps JSON illisible, vide, ou racine qui n'est pas un objet | 400 |
| Pas de session active (sauf `/api/health` et `/api/session`) | 401 |
| Plat introuvable | 404 |
| Un plat porte déjà ce nom | 409, avec `champs.nom` |
| Aucun autre plat ne convient pour le repas à changer (`/api/menu/regenerer`) | 409 |
| Validation en échec, ou catégorie principale inconnue | 422, avec `champs` |
| Identifiant ou mot de passe incorrect | 401 |
| Trop de tentatives de connexion | 429 |

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

-- Schéma de référence de menus_generator (voir doc/modelisation-bdd.md).
-- Rejouable : chaque instruction est sans effet si l'objet existe déjà.
-- Les clés étrangères sont activées par la connexion (voir src/Database/Database.php).

CREATE TABLE IF NOT EXISTS categorie_principale (
  id INTEGER PRIMARY KEY,
  libelle TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS plat (
  id INTEGER PRIMARY KEY,
  nom TEXT NOT NULL,
  nom_normalise TEXT NOT NULL UNIQUE,
  convient_dejeuner INTEGER NOT NULL DEFAULT 1 CHECK (convient_dejeuner IN (0, 1)),
  convient_diner INTEGER NOT NULL DEFAULT 1 CHECK (convient_diner IN (0, 1)),
  saison TEXT NOT NULL CHECK (saison IN ('printemps_ete', 'automne_hiver', 'hybride')),
  type TEXT NOT NULL CHECK (type IN ('classique', 'vegetarien')),
  niveau TEXT NOT NULL CHECK (niveau IN ('rapide', 'elabore')),
  categorie_principale_id INTEGER NOT NULL REFERENCES categorie_principale (id),
  poids INTEGER NOT NULL DEFAULT 2 CHECK (poids BETWEEN 1 AND 3),
  cree_le TEXT NOT NULL,
  modifie_le TEXT NOT NULL,
  -- Au moins un des deux repas doit être autorisé : contrainte de table, donc après les colonnes
  CHECK (convient_dejeuner = 1 OR convient_diner = 1)
);

CREATE TABLE IF NOT EXISTS proposition (
  id INTEGER PRIMARY KEY,
  plat_id INTEGER NOT NULL REFERENCES plat (id) ON DELETE CASCADE,
  date_menu TEXT NOT NULL,
  jour INTEGER NOT NULL CHECK (jour BETWEEN 1 AND 7),
  repas TEXT NOT NULL CHECK (repas IN ('dejeuner', 'diner')),
  origine TEXT NOT NULL CHECK (origine IN ('suggestion', 'choix_libre')),
  cree_le TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_proposition_plat_date ON proposition (plat_id, date_menu DESC);
CREATE INDEX IF NOT EXISTS idx_proposition_date ON proposition (date_menu);

-- Données de référence du PRD §5.1
INSERT OR IGNORE INTO categorie_principale (libelle) VALUES
  ('pâtes'),
  ('riz / céréales'),
  ('viande'),
  ('poisson'),
  ('œufs'),
  ('légumes'),
  ('soupe'),
  ('autre');

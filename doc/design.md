# Design de l'application

Ce document fixe le design du frontend de `menus_generator` : ambiance, palette, écrans, composants, accessibilité et ton. Il sert de référence au jalon 2 (voir [ROADMAP.md](../ROADMAP.md)). Les besoins sont dans [PRD.md](../PRD.md), le contrat de l'API dans [api.md](api.md).

Les couleurs et les écrans ci-dessous sont une première proposition, à ajuster à l'usage. Toute modification de la palette doit s'accompagner d'un nouveau calcul de contraste (voir §2).

## 1. Principes

| Décision | Choix | Pourquoi |
|---|---|---|
| Ambiance | Chaleureuse et gourmande : tons chauds, coins arrondis, esprit carnet de recettes | L'application sert à donner envie de cuisiner |
| Appareil | Téléphone d'abord, puis élargi à l'ordinateur (mobile-first) | Usage en cuisine ou pour les courses ; le CSS part du petit écran |
| Thème | Clair seulement | Un seul jeu de contrastes à vérifier |
| Menu | Grille de 7 jours, déjeuner et dîner par jour | Vue d'ensemble immédiate de la semaine |
| Navigation | Barre d'onglets en bas (« Menu », « Mes plats »), en haut sur ordinateur | À portée du pouce sur téléphone |
| Accueil | Le générateur de menu, juste après la connexion | C'est l'action principale du produit |
| Plat | Nom en gras, puis pastilles colorées avec icône | Lisible d'un coup d'œil, sans lire une ligne de texte |
| Formulaire de plat | Écran dédié plein écran, sans fenêtre superposée | Plus simple au clavier, sur petit écran et pour l'accessibilité |
| Ton | Tutoiement chaleureux | Cohérent avec l'ambiance ; voir §7 |
| Typographie | Polices du système, aucune police web | Chargement immédiat, aucune dépendance externe |
| Visuels | Pas de photo ; illustrations décoratives légères en SVG | La base est saisie à la main, il n'y a pas d'image de plat |
| Avertissements | Bandeau en haut du menu et pastille « À vérifier » sur le repas concerné | Un problème global ne passe pas inaperçu |

## 2. Palette

Les couleurs sont des variables CSS déclarées une fois sur `:root`. Aucune couleur n'est écrite en dur ailleurs.

### Couleurs de base

| Variable | Valeur | Usage |
|---|---|---|
| `--creme` | `#FBF5EC` | Fond de page |
| `--surface` | `#FFFFFF` | Cartes, champs |
| `--brun` | `#3B2A20` | Texte principal |
| `--brun-doux` | `#6B5648` | Texte secondaire |
| `--terracotta` | `#B5472A` | Action principale, onglet actif |
| `--terracotta-fonce` | `#98391F` | Survol et appui de l'action principale |
| `--lien` | `#A33F24` | Liens sur fond crème |
| `--bordure` | `#8A7060` | Bordure des champs |
| `--focus` | `#1F5FA8` | Contour de focus |
| `--danger` | `#A8261F` | Suppression, erreurs |
| `--danger-fond` | `#FBE4E1` | Fond des messages d'erreur |
| `--alerte-bord` | `#A87A00` | Bordure des avertissements |
| `--alerte-fond` | `#FFF1CC` | Fond des avertissements |
| `--alerte-texte` | `#5C4300` | Texte des avertissements |

### Pastilles

| Pastille | Texte | Fond | Icône |
|---|---|---|---|
| Printemps-été | `#6B4A00` | `#FCE9B8` | Soleil |
| Automne-hiver | `#7A3418` | `#F3D9CB` | Feuille |
| Hybride | `#3E5A22` | `#E4EBD3` | Soleil et feuille |
| Végétarien | `#2F5A1E` | `#DCEBD0` | Brin d'herbe |
| Rapide | `#164E63` | `#D6EDF3` | Éclair |
| Élaboré | `#5B2A6B` | `#EBDDF0` | Casserole |

Chaque pastille porte toujours **une icône et un texte** : l'information ne passe jamais uniquement par la couleur.

### Contrastes vérifiés (WCAG 2.1 AA)

Seuils : 4,5:1 pour le texte, 3:1 pour les éléments d'interface (bordures, contours de focus). Ratios calculés avec la formule de luminance relative du WCAG.

| Couple | Ratio | Seuil |
|---|---|---|
| Texte `#3B2A20` sur crème | 12,60 | 4,5 |
| Texte `#3B2A20` sur surface | 13,65 | 4,5 |
| Texte doux `#6B5648` sur crème | 6,35 | 4,5 |
| Texte doux `#6B5648` sur surface | 6,89 | 4,5 |
| Blanc sur `--terracotta` | 5,39 | 4,5 |
| Blanc sur `--terracotta-fonce` | 7,14 | 4,5 |
| `--lien` sur crème | 5,88 | 4,5 |
| Blanc sur `--danger` | 7,09 | 4,5 |
| Texte d'erreur `--danger` sur `--danger-fond` | 5,84 | 4,5 |
| Texte d'avertissement sur `--alerte-fond` | 8,28 | 4,5 |
| Pastille printemps-été | 6,71 | 4,5 |
| Pastille automne-hiver | 6,69 | 4,5 |
| Pastille hybride | 6,36 | 4,5 |
| Pastille végétarien | 6,47 | 4,5 |
| Pastille rapide | 7,49 | 4,5 |
| Pastille élaboré | 8,11 | 4,5 |
| Onglet actif (`--terracotta` sur surface) | 5,39 | 4,5 |
| Contour de focus sur crème | 5,94 | 3 |
| Bordure de champ sur surface | 4,60 | 3 |
| Bordure d'avertissement sur crème | 3,56 | 3 |
| Bordure d'avertissement sur `--alerte-fond` | 3,43 | 3 |

## 3. Typographie et espacements

- **Police** : pile système, `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`. Aucun fichier de police n'est chargé.
- **Tailles** : texte courant 1 rem (16 px minimum, pour éviter le zoom automatique sur iOS dans les champs) ; texte secondaire 0,875 rem ; titre d'écran 1,5 rem ; grand titre (connexion) 2 rem. Interligne 1,5 pour le texte courant.
- **Espacement** : unité de base de 0,5 rem ; les marges et remplissages en sont des multiples (0,5 / 1 / 1,5 / 2 rem).
- **Coins** : rayon de 0,75 rem pour les cartes et les champs, 999 px pour les pastilles et les boutons principaux.
- **Ombres** : une seule ombre douce pour les cartes, `0 1px 3px` brun à 12 % d'opacité.
- **Largeur** : contenu centré, 40 rem au maximum pour les formulaires, 64 rem pour la grille du menu.

## 4. Écrans

Le point de rupture entre téléphone et ordinateur est à 48 rem (768 px). Le CSS est écrit pour le téléphone, puis complété par une requête `min-width: 48rem`.

### 4.1 Connexion

```
+------------------------------+
|        [illustration]        |
|   Bienvenue dans tes menus   |
|                              |
|  Identifiant                 |
|  [______________________]    |
|  Mot de passe                |
|  [______________________]    |
|                              |
|  [      Se connecter     ]   |
+------------------------------+
```

- Pas de barre d'onglets tant que l'utilisateur n'est pas connecté.
- Erreurs : « Identifiant ou mot de passe incorrect. » (401), « Trop de tentatives, réessaie plus tard. » (429). Le message est affiché sous le formulaire et annoncé aux lecteurs d'écran.
- L'écran est affiché quand `GET /api/session` renvoie `connecte: false`, ou quand n'importe quel appel renvoie 401.

### 4.2 Menu (accueil)

```
+------------------------------+
| Ton menu de la semaine       |
|                              |
| Déjeuners [ 5 v]  Dîners [ 5 v]
| Repas végétariens            |
| ( ) Aucun (o) Quelques-uns   |
| ( ) Majoritairement          |
| Date de référence [________] |
|                              |
| [      Générer le menu    ]  |
+------------------------------+
| ! Pas assez de plats rapides |   <- bandeau d'avertissement
+------------------------------+
| Lundi                        |
|  Déjeuner  Quiche lorraine   |
|            [Soleil][Rapide]  |
|            [Changer] [Choisir]
|  Dîner     Soupe de potiron  |
|            [Feuille][Rapide] |
|            [Changer] [Choisir]
| Mardi ...                    |
+------------------------------+
| [  Menu  ]   [ Mes plats ]   |  <- barre d'onglets
+------------------------------+
```

- Réglages repliables une fois le menu généré, pour laisser la place au planning.
- Sur ordinateur, la grille passe en tableau : une ligne par repas (déjeuner, dîner), une colonne par jour.
- Actions par repas : « Changer » (nouvelle suggestion), « Choisir » (choix libre d'un plat existant). Action globale : « Tout régénérer ».
- Base vide : l'illustration d'écran vide, le texte « Ta base est vide. Ajoute quelques plats pour que je puisse composer ton menu. » et un bouton vers « Mes plats ».
- Avertissements : un bandeau en haut du menu résume les écarts (base insuffisante, niveau de repli, choix libre hors règles). Chaque repas concerné porte en plus une pastille « À vérifier ».

### 4.3 Mes plats

- En-tête avec le titre, le nombre de plats et le bouton « Ajouter un plat ».
- Liste de cartes, triée par nom. Chaque carte contient le nom en gras et les pastilles (saison, végétarien, niveau), plus un indicateur du poids (rarement, normal, favori).
- Recherche par nom en haut de la liste, quand la base dépasse 10 plats.
- Toucher une carte ouvre le formulaire de modification.
- Liste vide : illustration et « Ajoute ton premier plat ».

### 4.4 Formulaire de plat

- Écran plein écran, avec un bouton « Retour » en haut.
- Champs : nom, repas autorisés (cases « Déjeuner » et « Dîner »), saison, type, niveau, catégorie principale, poids de préférence.
- Les choix à trois valeurs ou moins (saison, type, niveau, poids) sont des boutons radio en grands boutons tactiles ; la catégorie principale est une liste déroulante.
- Erreurs de validation : message sous le champ concerné, d'après `champs` de la réponse d'API. L'erreur `repas` est affichée sous les deux cases à cocher. Doublon de nom (409) : « Un plat porte déjà ce nom. ».
- En modification, un bouton « Supprimer ce plat » (style destructif) demande une confirmation avant d'envoyer la requête.

### 4.5 États transverses

- **API indisponible** : bandeau « Impossible de joindre le serveur. Réessaie dans un instant. » avec un bouton « Réessayer ».
- **Chargement** : le bouton concerné passe en état désactivé avec le texte « Un instant… » ; la région de statut annonce l'attente.
- **Session expirée** : retour à l'écran de connexion, avec le message « Ta session a expiré, reconnecte-toi. ».

## 5. Composants

| Composant | Description |
|---|---|
| Barre d'onglets | Deux entrées (« Menu », « Mes plats ») plus « Se déconnecter » ; icône et texte ; onglet actif en `--terracotta` avec `aria-current="page"` ; fixe en bas sur téléphone, en haut sur ordinateur |
| Pastille | Icône SVG et texte, coins arrondis, couleurs du §2 |
| Carte de repas | Jour, repas (déjeuner ou dîner), nom du plat, pastilles, actions « Changer » et « Choisir » ; pastille « À vérifier » en cas d'écart |
| Bandeau d'avertissement | Fond `--alerte-fond`, bordure `--alerte-bord`, icône, texte `--alerte-texte`, `role="status"` |
| Bouton principal | Fond `--terracotta`, texte blanc, rayon arrondi, 44 px de haut au minimum |
| Bouton secondaire | Fond transparent, bordure `--terracotta`, texte `--terracotta` |
| Bouton destructif | Fond `--danger`, texte blanc ; toujours précédé d'une confirmation |
| Champ de formulaire | Libellé visible au-dessus, bordure `--bordure`, message d'erreur lié au champ par `aria-describedby` |
| Confirmation de suppression | Écran ou zone dédiée avec « Supprimer ce plat ? » ; boutons « Supprimer » (destructif) et « Annuler » (secondaire) ; le focus part sur « Annuler » |
| Confirmation de déconnexion | Fenêtre modale (`<dialog>`) avec « Se déconnecter ? » ; boutons « Se déconnecter » (principal) et « Annuler » (secondaire) ; le focus part sur « Annuler », Échap annule, le focus revient sur le bouton d'origine à la fermeture |

## 6. Accessibilité

Exigence du PRD §6 : interface utilisable au clavier, conforme WCAG 2.1 niveau AA.

- **Focus visible** : contour de 3 px `--focus` avec un décalage de 2 px sur tout élément interactif. Jamais de `outline: none` sans remplacement.
- **Clavier** : tout est atteignable et utilisable à la touche Tab ; l'ordre de tabulation suit l'ordre visuel ; Entrée valide un formulaire.
- **Zones tactiles** : 44 × 44 px au minimum, avec un espace d'au moins 8 px entre deux cibles.
- **Libellés** : chaque champ a un `<label>` visible, associé par `for`. Les groupes de boutons radio sont dans un `<fieldset>` avec un `<legend>`.
- **Annonces d'état** : une région `aria-live="polite"` annonce « Menu généré », « Plat enregistré », « Plat supprimé » ; les erreurs sont annoncées avec `role="alert"`.
- **Icônes** : les icônes décoratives sont masquées (`aria-hidden="true"`), car le texte à côté porte le sens. Les illustrations sont décoratives et sans texte alternatif.
- **Couleur** : jamais seule porteuse d'information (voir les pastilles du §2).
- **Titres** : un `<h1>` par écran, hiérarchie sans saut.
- **Mouvement** : les animations sont courtes et désactivées sous `prefers-reduced-motion: reduce`.
- **Zoom** : mise en page utilisable jusqu'à 200 % de zoom, sans défilement horizontal.

## 7. Ton et textes

- Tutoiement, phrases courtes, ton chaleureux sans excès. Pas de point d'exclamation sauf pour un succès.
- Les textes d'erreur viennent de l'API (`erreur`, `champs`) et sont affichés tels quels, comme prévu dans [api.md](api.md). Ils sont à la forme impersonnelle, donc sans conflit avec le tutoiement.
- Textes de l'interface :

| Situation | Texte |
|---|---|
| Titre de connexion | Bienvenue dans tes menus |
| Bouton de connexion | Se connecter |
| Titre du menu | Ton menu de la semaine |
| Bouton de génération | Générer le menu |
| Régénération globale | Tout régénérer |
| Régénération d'un repas | Changer |
| Choix libre | Choisir un plat |
| Base vide (menu) | Ta base est vide. Ajoute quelques plats pour que je puisse composer ton menu. |
| Liste de plats vide | Ajoute ton premier plat |
| Succès d'enregistrement | Plat enregistré |
| Confirmation de suppression | Supprimer ce plat ? Cette action est définitive. |
| Confirmation de déconnexion | Se déconnecter ? |
| API injoignable | Impossible de joindre le serveur. Réessaie dans un instant. |
| Session expirée | Ta session a expiré, reconnecte-toi. |

## 8. Contraintes techniques

- CSS natif, sans framework ni préprocesseur ; un fichier `public/css/` chargé par `index.html`.
- JavaScript en modules ES, sans build (voir [CLAUDE.md](../CLAUDE.md)). Les appels réseau passent tous par `public/js/api.js`.
- Pas d'`innerHTML` avec des données utilisateur : les éléments sont créés avec `createElement` et `textContent`. Le nom d'un plat est une donnée utilisateur.
- Les icônes et illustrations sont des SVG inline, sans fichier image ni bibliothèque.
- Une seule page `index.html`, avec des sections affichées ou masquées selon l'état (connexion, menu, liste, formulaire). La navigation ne recharge pas la page.

# PRD : menus_generator

Version 0.2 (brouillon). Les points marqués **[À préciser]** restent ouverts.

## 1. Problème et contexte

Chaque semaine, composer les repas (déjeuner et dîner) demande du temps et tourne souvent en rond : on reprend les mêmes plats, on oublie des idées de saison, et on ne sait pas quoi cuisiner en semaine (peu de temps) par rapport au week-end (plus de temps).

`menus_generator` propose automatiquement un menu hebdomadaire d'idées de repas à partir d'une base de plats que l'utilisateur constitue lui-même. Le menu tient compte de la saison en cours et du rythme de la semaine.

## 2. Utilisateurs et cas d'usage

**Utilisateur** : un foyer, seul utilisateur de l'application. Il n'y a ni gestion de plusieurs profils, ni inscription : un compte unique protège l'accès (voir §6).

Cas d'usage principaux :

- **Alimenter la base de plats** : l'utilisateur ajoute, modifie et supprime des plats, avec leurs caractéristiques (saison, type, rapide ou élaboré, végétarien ou non).
- **Générer un menu de la semaine** : l'utilisateur choisit les jours où il veut une idée de déjeuner et ceux où il veut une idée de dîner, et reçoit un menu sur sept jours.
- **Avoir des idées de saison** : le menu privilégie les plats de la saison en cours.
- **Adapter le temps de cuisine au jour** : plats rapides en semaine, plats élaborés le week-end.
- **Varier les types de repas** : proposer des repas végétariens selon le réglage choisi.
- **Régénérer** un menu ou un repas en particulier.

## 3. Objectifs et critères de succès

| Objectif | Critère de succès |
|---|---|
| Générer un menu rapidement | Menu affiché en moins de 3 s |
| Respecter la saison | Au moins 80 % des plats d'un menu appartiennent à la saison en cours ou sont hybrides |
| Distinguer semaine et week-end | Au moins 90 % des dîners et déjeuners de semaine sont des plats rapides, et au moins 90 % de ceux du week-end sont des plats élaborés, quand la base le permet |
| Éviter la répétition | Aucun plat identique dans une même semaine |
| Donner les idées demandées | 100 % des menus contiennent exactement un déjeuner à chaque jour de déjeuner choisi et un dîner à chaque jour de dîner choisi |
| Base suffisante | L'application indique, pour chaque catégorie, si la base contient assez de plats pour une semaine complète |

## 4. Périmètre

### Dans la V1

- Application web, utilisée par un seul foyer.
- Gestion de la base de plats : ajout, modification, suppression.
- Choix des jours de déjeuner et des jours de dîner.
- Génération d'un menu sur sept jours (lundi à dimanche).
- Prise en compte automatique de la saison, déduite de la date du jour (hémisphère nord).
- Réglage de la part de repas végétariens.
- Distinction semaine / week-end, à partir du caractère « rapide » ou « élaboré » que l'utilisateur attribue à chaque plat.
- Régénération d'un menu, ou d'un repas en particulier.

### Hors périmètre

- Enregistrement ou partage des menus. Un journal des plats proposés, sans menu, est prévu pour éviter les redites (voir `doc/modelisation-bdd.md`).
- Comptes utilisateurs, plusieurs profils, synchronisation entre appareils.
- Nombre de personnes à table (pas de calcul de quantités).
- Liste de courses.
- Recettes détaillées pas à pas (la V1 gère une base de plats et des idées, pas des recettes complètes).
- Suivi nutritionnel ou calorique.
- Exclusion d'aliments ou gestion des allergies (tous les aliments sont autorisés).
- Menus spéciaux pour les jours de fête (Noël, Pâques, etc.).
- Génération de plats par un modèle de langage : la base est saisie manuellement.

## 5. Exigences fonctionnelles

### 5.1 Base de plats

| Champ | Type | Obligatoire | Règle |
|---|---|---|---|
| Nom | Texte | Oui | Unique dans la base |
| Catégorie de repas | Choix : déjeuner, dîner, les deux | Oui | Par défaut : les deux |
| Saison | Choix : printemps-été, automne-hiver, hybride | Oui | Un seul choix |
| Type | Choix : classique, végétarien | Oui | Un seul choix |
| Catégorie principale | Choix : pâtes, riz / céréales, viande, poisson, œufs, légumes, soupe, autre | Oui | Un seul choix ; sert à la règle de variété déjeuner / dîner (§5.4) |
| Poids de préférence | Choix : rarement, normal, favori | Non | Par défaut : normal. Influence le tirage des propositions, sans l'imposer (§5.4) |
| Niveau | Choix : rapide, élaboré | Oui | Défini manuellement par l'utilisateur |

- L'utilisateur peut ajouter, modifier et supprimer un plat.
- La suppression d'un plat est libre et sans condition. Elle ne modifie pas le menu déjà affiché : aucun menu n'est stocké côté serveur. L'historique du plat dans le journal des propositions est effacé en même temps que lui.

### 5.2 Entrées du générateur

| Champ | Type | Obligatoire | Règle |
|---|---|---|---|
| Jours de déjeuner | Liste de jours (lundi à dimanche) | Oui | De 1 à 7 jours distincts ; le nombre de déjeuners du menu en découle |
| Jours de dîner | Liste de jours (lundi à dimanche) | Oui | De 1 à 7 jours distincts ; choisis indépendamment des jours de déjeuner |
| Proportion de repas végétariens | Choix : aucun / quelques-uns / majoritairement | Non | Par défaut : « quelques-uns », soit un repas végétarien au maximum par semaine. « Majoritairement » signifie plus de la moitié des repas du menu, soit au moins 8 repas sur 14 |
| Date de référence | Date | Non | Par défaut : date du jour ; sert à déterminer la saison |

### 5.3 Sorties

- Une liste d'idées de déjeuner et une liste d'idées de dîner, avec un repas par jour choisi.
- Un planning sur sept jours associant un déjeuner et un dîner aux jours choisis.
- Pour chaque repas : nom, saison, type, niveau (rapide ou élaboré).
- Un message explicite si la base ne permet pas de remplir le menu demandé.

### 5.4 Règles métier

**Saisons**

- Printemps-été : mars à août.
- Automne-hiver : septembre à février.
- Un plat est étiqueté **printemps-été**, **automne-hiver**, ou **hybride** (mangé toute l'année, par exemple soupe de légumes ou salade de pâtes tièdes).
- Un menu est composé en priorité de plats de la saison en cours, complétés par des plats hybrides.
- En automne-hiver, la part de plats automne-hiver est supérieure à celle des plats printemps-été. C'est l'inverse en printemps-été.
- La part des plats hybrides est de 30 à 40 % des repas de chaque menu.
- Les plats de la saison opposée sont exclus.

**Semaine et week-end**

- Du lundi au vendredi : plats de niveau **rapide** en priorité.
- Le samedi et le dimanche : plats de niveau **élaboré** en priorité.
- Si la base ne contient pas assez de plats du niveau attendu, le générateur complète avec l'autre niveau et l'indique à l'utilisateur. Décision prise : le générateur complète avec l'autre niveau et le signale, plutôt que de refuser le menu.

**Variété**

- Aucun plat identique dans la même semaine.
- Un déjeuner et un dîner du même jour ne sont pas le même type de plat (par exemple pas deux plats de pâtes), en priorité : la contrainte est souple et s'applique si la base le permet. Le « type » d'un plat est sa catégorie principale (voir §5.1).
- La proportion de repas végétariens suit le réglage choisi (section 5.2).
- Un plat proposé récemment est moins souvent tiré, et un plat jamais proposé est favorisé (règle de fraîcheur). Le poids de préférence module ce tirage.

**Régénération**

- Régénérer le menu ou un repas remplace la proposition sans modifier les autres repas, sauf si l'utilisateur régénère tout le menu.

**Modification d'un repas proposé**

- Pour chaque repas d'une liste ou du planning, l'utilisateur peut au choix :
  - demander une nouvelle suggestion pour ce repas (régénération ciblée, règles de la section 5.4 appliquées) ;
  - choisir librement un plat existant dans la base. Le choix libre n'est pas soumis aux règles de saison, de niveau ou de variété ; l'application peut l'indiquer sans l'empêcher.
- Le choix libre ne permet pas de créer un plat depuis le menu. La création reste dans la gestion de la base (section 5.1). Décision prise : cela évite les plats saisis à la volée, avec des caractéristiques incomplètes.

## 6. Exigences non fonctionnelles

- **Langue** : interface et contenu en français.
- **Plateforme** : application web, utilisable sur ordinateur et sur téléphone.
- **Performance** : génération d'un menu en moins de 3 secondes avec une base de 200 plats.
- **Accessibilité** : interface utilisable au clavier, conformité WCAG 2.1 niveau AA.
- **Confidentialité** : accès protégé par un compte unique (identifiant et mot de passe, pas d'inscription ni de gestion de plusieurs profils), pas de donnée personnelle obligatoire. La base de plats est stockée sur un hébergement privé (o2switch).
- **Fiabilité** : le menu généré respecte les règles de la section 5.4. Si la base est insuffisante, l'application l'indique plutôt que de violer une règle.

## 7. Hypothèses, contraintes et questions ouvertes

### Décisions validées

- Plateforme : application web.
- Utilisateur : un seul foyer, protégé par un compte unique (identifiant et mot de passe configurés hors dépôt, pas d'inscription, pas de gestion de plusieurs profils).
- Source des plats : saisie manuelle par l'utilisateur, pas de génération par IA.
- Pas d'enregistrement de menus.
- Pas de gestion du nombre de personnes.
- Pas d'exclusion d'aliments.
- Saisons regroupées en deux ensembles : printemps-été et automne-hiver.
- Jours de fête non pris en compte.
- Le caractère « rapide » ou « élaboré » est défini par l'utilisateur pour chaque plat, et non calculé à partir d'un temps de préparation.
- Suppression d'un plat : libre et sans condition, même si le plat figure dans un menu affiché (§5.1).

### Hypothèses

- L'application est utilisée en France (saisons de l'hémisphère nord, cuisine francophone).
- Les jours de déjeuner et les jours de dîner sont indépendants : l'utilisateur peut demander plus de dîners que de déjeuners, ou des repas à des jours différents.
- Le volume de la base reste modeste (quelques centaines de plats au maximum).

### Décisions sur les questions ouvertes

1. **Sens de « idées » demandées** : un plat distinct par repas choisi, pour chaque type de repas (déjeuners et dîners), pas un total de repas. L'utilisateur ne saisit plus un nombre : il choisit les jours, séparément pour les déjeuners et les dîners, et le nombre en découle.
2. **Période** : menu fixe sur sept jours.
3. **Hybrides** : conservés comme catégorie à part entière.
4. **Saison opposée** : plats exclus du menu.
5. **Saisie de la base** : formulaire un par un en V1. Pas d'import CSV ou JSON pour l'instant.
6. **Hémisphère** : pas de réglage. La base de repas est générée par l'auteur du projet, et l'application ne prend en compte que l'hémisphère nord.

Il ne reste aucun point à préciser dans ce document.

### Contraintes

- Les règles de saison, de niveau et de variété doivent être validées avant le développement.

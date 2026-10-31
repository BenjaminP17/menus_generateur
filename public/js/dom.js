// Construction du DOM sans innerHTML : les données utilisateur passent toujours par des nœuds texte.

/**
 * Crée un élément. `attributs` : `class`, `on<Evenement>` (écouteur), booléens (true pose
 * l'attribut, false/null/undefined l'omet), le reste est posé tel quel. Les enfants chaîne
 * deviennent des nœuds texte ; null et false sont ignorés.
 */
export function el(balise, attributs = {}, ...enfants) {
    const noeud = document.createElement(balise);
    for (const [cle, valeur] of Object.entries(attributs)) {
        if (valeur === false || valeur === null || valeur === undefined) {
            continue;
        }
        if (cle.startsWith('on') && typeof valeur === 'function') {
            noeud.addEventListener(cle.slice(2).toLowerCase(), valeur);
        } else if (cle === 'class') {
            noeud.className = valeur;
        } else if (valeur === true) {
            noeud.setAttribute(cle, '');
        } else {
            noeud.setAttribute(cle, valeur);
        }
    }
    ajouter(noeud, enfants);

    return noeud;
}

function ajouter(parent, enfants) {
    for (const enfant of enfants.flat()) {
        if (enfant === null || enfant === false || enfant === undefined) {
            continue;
        }
        parent.append(typeof enfant === 'string' || typeof enfant === 'number'
            ? document.createTextNode(String(enfant))
            : enfant);
    }
}

/** Remplace le contenu d'un conteneur. */
export function remplacer(conteneur, ...enfants) {
    conteneur.replaceChildren();
    ajouter(conteneur, enfants);
}

/** Annonce un message aux lecteurs d'écran (région `aria-live="polite"`). */
export function annoncer(message) {
    const zone = document.getElementById('statut');
    zone.textContent = '';
    // Un tick plus tard, pour que le même message soit ré-annoncé.
    setTimeout(() => { zone.textContent = message; }, 50);
}

/** Majuscule initiale, pour afficher les libellés de catégories (« pâtes » devient « Pâtes »). */
export function capitaliser(texte) {
    return texte.charAt(0).toUpperCase() + texte.slice(1);
}

/** Minuscules sans accents, pour la recherche par nom côté navigateur. */
export function normaliser(texte) {
    return texte.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}

// Composants réutilisables : pastilles, champs de formulaire, bandeaux (doc/design.md §5).

import { el } from './dom.js';
import { icone } from './icons.js';

const PASTILLES = {
    printemps_ete: { classe: 'printemps-ete', icone: 'soleil', texte: 'Printemps-été' },
    automne_hiver: { classe: 'automne-hiver', icone: 'feuille', texte: 'Automne-hiver' },
    hybride: { classe: 'hybride', icone: 'soleil-feuille', texte: 'Hybride' },
    vegetarien: { classe: 'vegetarien', icone: 'herbe', texte: 'Végétarien' },
    rapide: { classe: 'rapide', icone: 'eclair', texte: 'Rapide' },
    elabore: { classe: 'elabore', icone: 'casserole', texte: 'Élaboré' },
};

export const POIDS = { 1: 'Rarement', 2: 'Normal', 3: 'Favori' };

/** Pastille colorée : toujours une icône et un texte, jamais la couleur seule. */
export function pastille(cle) {
    const definition = PASTILLES[cle];

    return el('span', { class: `pastille pastille-${definition.classe}` }, icone(definition.icone), definition.texte);
}

/** Pastilles d'un plat : saison, végétarien (si c'est le cas), niveau. */
export function pastillesDuPlat(plat) {
    return el(
        'span',
        { class: 'pastilles' },
        pastille(plat.saison),
        plat.type === 'vegetarien' ? pastille('vegetarien') : null,
        pastille(plat.niveau),
    );
}

/** Zone de message d'erreur liée à un champ (aria-describedby), vide tant qu'il n'y a pas d'erreur. */
export function messageChamp(id) {
    return el('p', { class: 'champ-erreur', id, hidden: true });
}

export function afficherMessage(zone, message) {
    zone.textContent = message ?? '';
    zone.hidden = !message;
}

let compteur = 0;
export function nouvelId(prefixe) {
    compteur += 1;

    return `${prefixe}-${compteur}`;
}

/**
 * Champ texte avec libellé visible. Renvoie `{ racine, entree, erreur }` ; `erreur` est la zone
 * à passer à `afficherMessage`.
 */
export function champTexte({ libelle, nom, type = 'text', valeur = '', autocomplete, maxlength, requis = false }) {
    const id = nouvelId(nom);
    const erreur = messageChamp(`${id}-erreur`);
    const entree = el('input', {
        id,
        name: nom,
        type,
        value: valeur,
        autocomplete,
        maxlength,
        required: requis,
        'aria-describedby': erreur.id,
    });

    return { racine: el('div', { class: 'champ' }, el('label', { for: id }, libelle), entree, erreur), entree, erreur };
}

/**
 * Groupe de boutons radio présentés en grands boutons tactiles. `options` : [[valeur, libellé]].
 * Renvoie `{ racine, valeur(), erreur }`.
 */
export function groupeRadio({ legende, nom, options, valeur }) {
    const id = nouvelId(nom);
    const erreur = messageChamp(`${id}-erreur`);
    const choix = options.map(([v, libelle]) => {
        const idChoix = `${id}-${v}`;

        return el(
            'div',
            { class: 'choix' },
            el('input', { type: 'radio', id: idChoix, name: nom, value: v, checked: String(v) === String(valeur) }),
            el('label', { for: idChoix }, libelle),
        );
    });
    const racine = el('fieldset', { class: 'champ', 'aria-describedby': erreur.id }, el('legend', {}, legende), el('div', { class: 'choix-groupe' }, choix), erreur);

    return {
        racine,
        erreur,
        valeur: () => racine.querySelector('input:checked')?.value ?? null,
    };
}

/** Bandeau d'avertissement ou d'erreur (doc/design.md §5). */
export function bandeau(message, { type = 'alerte', action = null } = {}) {
    return el(
        'div',
        { class: `bandeau bandeau-${type}`, role: type === 'erreur' ? 'alert' : 'status' },
        icone('alerte'),
        el('p', {}, message),
        action,
    );
}

/** Bouton qui passe en état « Un instant… » le temps d'une action asynchrone. */
export async function pendantAction(bouton, action) {
    const texte = bouton.textContent;
    bouton.disabled = true;
    bouton.textContent = 'Un instant…';
    try {
        return await action();
    } finally {
        bouton.disabled = false;
        bouton.textContent = texte;
    }
}

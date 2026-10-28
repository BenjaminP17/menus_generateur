import { api } from '../api.js';
import { pastillesDuPlat, POIDS } from '../components.js';
import { el, normaliser } from '../dom.js';
import { icone, illustration } from '../icons.js';

const SEUIL_RECHERCHE = 10;

function carte(plat) {
    return el(
        'li',
        {},
        el(
            'a',
            { class: 'carte carte-plat', href: `#/plats/${plat.id}` },
            el('span', { class: 'carte-nom' }, plat.nom),
            pastillesDuPlat(plat),
            el('span', { class: 'carte-poids' }, plat.poids === 3 ? icone('etoile') : null, POIDS[plat.poids]),
        ),
    );
}

function lienAjout() {
    return el('a', { class: 'bouton bouton-principal', href: '#/plats/nouveau' }, icone('plus'), 'Ajouter un plat');
}

/** Liste des plats (doc/design.md §4.3). */
export async function vuePlats() {
    const plats = await api.plats();

    const enTete = el(
        'div',
        { class: 'en-tete' },
        el('div', {}, el('h1', {}, 'Mes plats'), el('p', { class: 'secondaire' }, plats.length === 1 ? '1 plat' : `${plats.length} plats`)),
        plats.length > 0 ? lienAjout() : null,
    );

    if (plats.length === 0) {
        return {
            titre: 'Mes plats',
            noeuds: el(
                'section',
                { class: 'ecran' },
                enTete,
                el('div', { class: 'vide' }, illustration(), el('p', {}, 'Ajoute ton premier plat'), lienAjout()),
            ),
        };
    }

    const liste = el('ul', { class: 'liste-cartes' });
    const aucun = el('p', { class: 'secondaire', hidden: true }, 'Aucun plat ne correspond à ta recherche.');
    const afficher = (filtre) => {
        const cherche = normaliser(filtre.trim());
        const visibles = plats.filter((plat) => normaliser(plat.nom).includes(cherche));
        liste.replaceChildren(...visibles.map(carte));
        aucun.hidden = visibles.length > 0;
    };

    let recherche = null;
    if (plats.length > SEUIL_RECHERCHE) {
        const entree = el('input', { id: 'recherche', type: 'search', autocomplete: 'off', onInput: () => afficher(entree.value) });
        recherche = el('div', { class: 'champ' }, el('label', { for: 'recherche' }, 'Rechercher un plat'), entree);
    }
    afficher('');

    return { titre: 'Mes plats', noeuds: el('section', { class: 'ecran' }, enTete, recherche, liste, aucun) };
}

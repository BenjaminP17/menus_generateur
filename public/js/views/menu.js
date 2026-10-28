import { el } from '../dom.js';
import { icone, illustration } from '../icons.js';

/** Écran du menu. Le générateur arrive au jalon 4 : en attendant, on renvoie vers la saisie des plats. */
export function vueMenu() {
    return {
        titre: 'Menu',
        noeuds: el(
            'section',
            { class: 'ecran' },
            el('h1', {}, 'Ton menu de la semaine'),
            el(
                'div',
                { class: 'vide' },
                illustration(),
                el('p', {}, 'Le générateur de menu arrive bientôt. En attendant, constitue ta base de plats.'),
                el('a', { class: 'bouton bouton-principal', href: '#/plats' }, icone('plats'), 'Mes plats'),
            ),
        ),
    };
}

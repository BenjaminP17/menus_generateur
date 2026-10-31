// Icônes et illustrations en SVG inline, toutes décoratives (aria-hidden) : le texte voisin porte le sens.

const NS = 'http://www.w3.org/2000/svg';

// Tracés sur une grille 24 x 24, dessinés au trait.
const TRACES = {
    soleil: ['M12 8a4 4 0 1 0 0 8 4 4 0 0 1 0-8z', 'M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1'],
    feuille: ['M5 19C5 10 10 5 19 5c0 9-5 14-14 14z', 'M5 19l8-8'],
    'soleil-feuille': ['M8 4a3 3 0 1 0 0 6 3 3 0 0 1 0-6z', 'M8 1v1.5M1 7h1.5M3 2l1 1', 'M10 20c0-6 4-10 11-10 0 6-4 10-11 10z', 'M10 20l6-6'],
    herbe: ['M12 21V10', 'M12 14c-4 0-6-3-6-7 4 0 6 3 6 7z', 'M12 16c3 0 6-2 6-6-4 0-6 2-6 6z'],
    eclair: ['M13 2L4 14h7l-1 8 9-12h-7z'],
    casserole: ['M4 10h16v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z', 'M2 10h2M20 10h2', 'M9 7c0-1.5 1-1.5 1-3M14 7c0-1.5 1-1.5 1-3'],
    menu: ['M4 5h16v15H4z', 'M4 10h16M8 3v4M16 3v4'],
    plats: ['M3 11h18a9 9 0 0 1-18 0z', 'M8 7c0-2 1.5-2 1.5-4M13 7c0-2 1.5-2 1.5-4'],
    sortie: ['M10 4H5v16h5', 'M15 8l5 4-5 4M20 12H9'],
    alerte: ['M12 3l10 18H2z', 'M12 10v5M12 18v.5'],
    retour: ['M15 5l-7 7 7 7'],
    plus: ['M12 5v14M5 12h14'],
    etoile: ['M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8 6.6 19.7l1.1-6.1L3.2 9.4l6.1-.8z'],
};

/** Icône de 1,25 rem, au trait, qui hérite de la couleur du texte. */
export function icone(nom) {
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', 'icone');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    for (const d of TRACES[nom]) {
        const chemin = document.createElementNS(NS, 'path');
        chemin.setAttribute('d', d);
        svg.append(chemin);
    }

    return svg;
}

/** Illustration d'un bol fumant, pour la connexion et les écrans vides. */
export function illustration() {
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 120 90');
    svg.setAttribute('class', 'illustration');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');

    const elements = [
        ['path', { d: 'M14 46h92a46 40 0 0 1-92 0z', class: 'illustration-bol' }],
        ['path', { d: 'M14 46h92', class: 'illustration-trait' }],
        ['path', { d: 'M40 84h40', class: 'illustration-trait' }],
        ['path', { d: 'M44 34c0-8 7-8 7-16M60 34c0-8 7-8 7-16M76 34c0-8 7-8 7-16', class: 'illustration-vapeur' }],
        ['circle', { cx: 42, cy: 60, r: 3, class: 'illustration-point' }],
        ['circle', { cx: 62, cy: 68, r: 3, class: 'illustration-point' }],
        ['circle', { cx: 80, cy: 58, r: 3, class: 'illustration-point' }],
    ];
    for (const [balise, attributs] of elements) {
        const noeud = document.createElementNS(NS, balise);
        for (const [cle, valeur] of Object.entries(attributs)) {
            noeud.setAttribute(cle, valeur);
        }
        svg.append(noeud);
    }

    return svg;
}

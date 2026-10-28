// Point d'entrée : session, routage par hash, barre de navigation (doc/design.md §4 et §5).

import { api, ApiError, onNonAuthentifie } from './api.js';
import { bandeau } from './components.js';
import { annoncer, el, remplacer } from './dom.js';
import { icone } from './icons.js';
import { vueConnexion } from './views/login.js';
import { vueMenu } from './views/menu.js';
import { vuePlatForm } from './views/plat-form.js';
import { vuePlats } from './views/plats.js';

const vue = document.getElementById('vue');
const banniere = document.getElementById('banniere');
const navigation = document.getElementById('navigation');
const liensNavigation = document.getElementById('navigation-liens');

const TITRE = 'Mes menus';
const ROUTE_PAR_DEFAUT = '#/plats';

let connecte = false;
let messageConnexion = '';
let rendu = 0;

function naviguer(hash) {
    if (window.location.hash === hash) {
        afficher();
    } else {
        window.location.hash = hash;
    }
}

function retourConnexion(message = '') {
    connecte = false;
    messageConnexion = message;
    afficher();
}

async function deconnecter() {
    try {
        await api.deconnexion();
    } catch {
        // Même si la requête échoue, on revient à l'écran de connexion.
    }
    window.location.hash = '';
    retourConnexion();
}

/**
 * Confirmation dans un <dialog> modal : le navigateur piège le focus, Échap annule, et le focus
 * part sur « Annuler » (doc/design.md §5). Le focus revient sur le bouton d'origine à la fermeture.
 */
function confirmerDeconnexion() {
    const declencheur = document.activeElement;
    const annuler = el('button', { type: 'button', class: 'bouton bouton-secondaire' }, 'Annuler');
    const confirmer = el('button', { type: 'button', class: 'bouton bouton-principal' }, 'Se déconnecter');
    const boite = el(
        'dialog',
        { class: 'confirmation-modale', 'aria-labelledby': 'deconnexion-titre' },
        el('p', { id: 'deconnexion-titre' }, 'Se déconnecter ?'),
        el('div', { class: 'actions' }, confirmer, annuler),
    );

    const fermer = () => {
        boite.close();
        boite.remove();
        declencheur?.focus?.();
    };
    annuler.addEventListener('click', fermer);
    boite.addEventListener('cancel', (evenement) => {
        evenement.preventDefault();
        fermer();
    });
    confirmer.addEventListener('click', async () => {
        boite.close();
        boite.remove();
        await deconnecter();
    });

    document.body.append(boite);
    boite.showModal();
    annuler.focus();
}

function barreNavigation(routeActive) {
    const lien = (hash, nomIcone, texte, actif) =>
        el('a', { href: hash, 'aria-current': actif ? 'page' : null }, icone(nomIcone), el('span', {}, texte));

    remplacer(
        liensNavigation,
        lien('#/menu', 'menu', 'Menu', routeActive === 'menu'),
        lien('#/plats', 'plats', 'Mes plats', routeActive === 'plats'),
        el(
            'button',
            {
                type: 'button',
                onClick: confirmerDeconnexion,
            },
            icone('sortie'),
            el('span', {}, 'Se déconnecter'),
        ),
    );
}

/** Résout le hash en `{ actif, creer }` ; `creer` renvoie la vue (ou une promesse). */
function resoudre(hash) {
    if (hash === '#/menu') {
        return { actif: 'menu', creer: () => vueMenu() };
    }
    if (hash === '#/plats') {
        return { actif: 'plats', creer: () => vuePlats() };
    }
    if (hash === '#/plats/nouveau') {
        return { actif: 'plats', creer: () => vuePlatForm({ naviguer }) };
    }
    const modification = /^#\/plats\/(\d{1,9})$/.exec(hash);
    if (modification) {
        return { actif: 'plats', creer: () => vuePlatForm({ id: modification[1], naviguer }) };
    }

    return null;
}

function montrer({ titre, noeuds, focus }) {
    remplacer(vue, noeuds);
    document.title = `${titre} · ${TITRE}`;
    if (focus) {
        focus();
    } else {
        vue.focus({ preventScroll: true });
    }
    window.scrollTo(0, 0);
    annoncer(titre);
}

function montrerErreur(erreur) {
    const injoignable = erreur instanceof ApiError && erreur.status === 0;
    const reessayer = el('button', { type: 'button', class: 'bouton bouton-secondaire', onClick: afficher }, 'Réessayer');
    remplacer(banniere, bandeau(erreur instanceof ApiError ? erreur.message : 'Une erreur inattendue est survenue.', { type: 'erreur', action: injoignable ? reessayer : null }));
}

async function afficher() {
    const ce = ++rendu;
    remplacer(banniere);

    if (!connecte) {
        navigation.hidden = true;
        const message = messageConnexion;
        messageConnexion = '';
        montrer(vueConnexion({
            message,
            surConnecte: () => {
                connecte = true;
                naviguer(ROUTE_PAR_DEFAUT);
            },
        }));

        return;
    }

    const hash = window.location.hash;
    const route = resoudre(hash);
    if (route === null) {
        naviguer(ROUTE_PAR_DEFAUT);

        return;
    }

    navigation.hidden = false;
    barreNavigation(route.actif);
    try {
        const resultat = await route.creer();
        if (ce === rendu) {
            montrer(resultat);
        }
    } catch (e) {
        if (ce !== rendu || (e instanceof ApiError && e.status === 401)) {
            return;
        }
        if (e instanceof ApiError && e.status === 404) {
            naviguer(ROUTE_PAR_DEFAUT);

            return;
        }
        montrerErreur(e);
    }
}

async function demarrer() {
    onNonAuthentifie(() => retourConnexion('Ta session a expiré, reconnecte-toi.'));
    window.addEventListener('hashchange', afficher);

    try {
        connecte = (await api.session()).connecte === true;
    } catch (e) {
        montrerErreur(e);

        return;
    }
    afficher();
}

demarrer();

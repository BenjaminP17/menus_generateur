// Seul module qui appelle fetch : tous les écrans passent par ici (doc/api.md).

export class ApiError extends Error {
    constructor(status, message, champs = {}) {
        super(message);
        this.status = status;
        this.champs = champs;
    }
}

const MESSAGE_INJOIGNABLE = 'Impossible de joindre le serveur. Réessaie dans un instant.';

let surNonAuthentifie = () => {};

/** Enregistre la réaction à un 401 hors connexion (session expirée). */
export function onNonAuthentifie(rappel) {
    surNonAuthentifie = rappel;
}

async function requete(methode, chemin, corps) {
    const options = {
        method: methode,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    };
    if (corps !== undefined) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(corps);
    }

    let reponse;
    try {
        reponse = await fetch(chemin, options);
    } catch {
        throw new ApiError(0, MESSAGE_INJOIGNABLE);
    }

    if (reponse.status === 204) {
        return null;
    }

    let donnees = null;
    try {
        donnees = await reponse.json();
    } catch {
        // Corps absent ou illisible : on s'appuie sur le code HTTP.
    }

    if (reponse.ok) {
        return donnees;
    }

    if (reponse.status === 401 && chemin !== '/api/session') {
        surNonAuthentifie();
    }

    const message = donnees?.erreur ?? (reponse.status >= 500
        ? 'Une erreur est survenue côté serveur.'
        : 'La requête a échoué.');
    throw new ApiError(reponse.status, message, donnees?.champs ?? {});
}

export const api = {
    session: () => requete('GET', '/api/session'),
    connexion: (identifiant, motDePasse) =>
        requete('POST', '/api/session', { identifiant, mot_de_passe: motDePasse }),
    deconnexion: () => requete('DELETE', '/api/session'),

    categories: async () => (await requete('GET', '/api/categories')).categories,
    plats: async () => (await requete('GET', '/api/plats')).plats,
    plat: (id) => requete('GET', `/api/plats/${encodeURIComponent(id)}`),
    creerPlat: (plat) => requete('POST', '/api/plats', plat),
    modifierPlat: (id, plat) => requete('PUT', `/api/plats/${encodeURIComponent(id)}`, plat),
    supprimerPlat: (id) => requete('DELETE', `/api/plats/${encodeURIComponent(id)}`),

    // Le menu n'est pas stocké côté serveur : chaque appel renvoie les réglages et le menu en
    // cours (`repas` : [{ jour, repas, plat_id }]) et reçoit le menu complet.
    genererMenu: async (reglages) => (await requete('POST', '/api/menu', reglages)).menu,
    regenererRepas: async (reglages, repas, cible) =>
        (await requete('POST', '/api/menu/regenerer', { ...reglages, repas, cible })).menu,
    choisirPlat: async (reglages, repas, cible, platId) =>
        (await requete('POST', '/api/menu/choisir', { ...reglages, repas, cible, plat_id: platId })).menu,
};

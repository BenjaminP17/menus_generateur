// Le menu en cours vit dans le navigateur : le serveur ne stocke rien (ROADMAP, jalon 4). Le
// stockage peut être absent ou refusé (navigation privée) : l'écran fonctionne sans.

const CLE = 'menus_generator.menu';

/** Menu gardé `{ reglages, menu }`, ou null s'il n'y en a pas ou s'il est illisible. */
export function lireMenu() {
    try {
        const brut = window.localStorage.getItem(CLE);
        if (brut === null) {
            return null;
        }
        const { reglages, menu } = JSON.parse(brut);
        const valide = Array.isArray(reglages?.jours_dejeuners)
            && Array.isArray(reglages?.jours_diners)
            && Array.isArray(menu?.repas)
            && Array.isArray(menu?.avertissements);

        return valide ? { reglages, menu } : null;
    } catch {
        return null;
    }
}

export function garderMenu(reglages, menu) {
    try {
        window.localStorage.setItem(CLE, JSON.stringify({ reglages, menu }));
    } catch {
        // Tant pis : le menu reste affiché, il ne survivra juste pas à un rechargement.
    }
}

export function oublierMenu() {
    try {
        window.localStorage.removeItem(CLE);
    } catch {
        // Rien à faire.
    }
}

// Partage du menu validé : un texte simple, envoyé depuis le navigateur. Le serveur ne garde
// aucun menu (ROADMAP, jalon 5b).

const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const REPAS = [['dejeuner', 'Déjeuner'], ['diner', 'Dîner']];

/** Un jour par ligne, du lundi au dimanche : « Lundi : déjeuner X, dîner Y ». */
export function texteDuMenu(menu, titre) {
    const lignes = [titre];
    for (const jour of JOURS) {
        const repas = REPAS
            .map(([cle, libelle]) => [libelle, menu.repas.find((r) => r.jour === jour && r.repas === cle)])
            .filter(([, entree]) => entree !== undefined)
            .map(([libelle, entree]) => `${libelle.toLowerCase()} ${entree.plat.nom}`);
        if (repas.length > 0) {
            lignes.push(`${jour.charAt(0).toUpperCase()}${jour.slice(1)} : ${repas.join(', ')}`);
        }
    }

    return lignes.join('\n');
}

/**
 * Ouvre la feuille de partage du téléphone, ou copie le texte quand elle n'existe pas.
 * Renvoie 'partage', 'copie' ou 'annule' ; lève une erreur si rien n'a pu se faire.
 */
export async function partager(titre, texte) {
    if (typeof navigator.share === 'function') {
        try {
            await navigator.share({ title: titre, text: texte });

            return 'partage';
        } catch (e) {
            if (e?.name === 'AbortError') {
                return 'annule';
            }
            // Partage refusé par le navigateur : on tente la copie.
        }
    }
    await navigator.clipboard.writeText(texte);

    return 'copie';
}

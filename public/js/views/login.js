import { api, ApiError } from '../api.js';
import { afficherMessage, champTexte, messageChamp, pendantAction } from '../components.js';
import { el } from '../dom.js';
import { illustration } from '../icons.js';

/** Écran de connexion (doc/design.md §4.1). `surConnecte` est appelé après une connexion réussie. */
export function vueConnexion({ message, surConnecte }) {
    const identifiant = champTexte({ libelle: 'Identifiant', nom: 'identifiant', autocomplete: 'username', requis: true });
    const motDePasse = champTexte({ libelle: 'Mot de passe', nom: 'mot_de_passe', type: 'password', autocomplete: 'current-password', requis: true });
    const erreur = messageChamp('connexion-erreur');
    erreur.setAttribute('role', 'alert');
    erreur.classList.add('champ-erreur-bloc');
    afficherMessage(erreur, message);

    const bouton = el('button', { type: 'submit', class: 'bouton bouton-principal' }, 'Se connecter');

    const formulaire = el(
        'form',
        {
            class: 'formulaire carte-connexion',
            novalidate: true,
            onSubmit: async (evenement) => {
                evenement.preventDefault();
                afficherMessage(erreur, '');
                if (identifiant.entree.value === '' || motDePasse.entree.value === '') {
                    afficherMessage(erreur, 'Renseigne ton identifiant et ton mot de passe.');

                    return;
                }
                try {
                    await pendantAction(bouton, () => api.connexion(identifiant.entree.value, motDePasse.entree.value));
                    motDePasse.entree.value = '';
                    surConnecte();
                } catch (e) {
                    if (!(e instanceof ApiError)) {
                        throw e;
                    }
                    afficherMessage(erreur, e.status === 429 ? 'Trop de tentatives, réessaie plus tard.' : e.message);
                }
            },
        },
        identifiant.racine,
        motDePasse.racine,
        erreur,
        bouton,
    );

    return {
        titre: 'Connexion',
        noeuds: el('section', { class: 'ecran ecran-connexion' }, illustration(), el('h1', {}, 'Bienvenue dans tes menus'), formulaire),
        focus: () => identifiant.entree.focus(),
    };
}

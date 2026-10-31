import { api, ApiError } from '../api.js';
import { afficherMessage, bandeau, champTexte, groupeRadio, messageChamp, nouvelId, pendantAction, POIDS } from '../components.js';
import { annoncer, capitaliser, el } from '../dom.js';
import { icone } from '../icons.js';

const NOM_MAX = 120;

// Champs du payload qui ont une zone d'erreur dans le formulaire ; le reste va dans le bandeau.
const CHAMPS_AFFICHES = ['nom', 'saison', 'type', 'niveau', 'categorie_principale_id', 'poids', 'repas'];

/**
 * Formulaire d'ajout (id absent) ou de modification d'un plat (doc/design.md §4.4).
 * `naviguer(hash)` change d'écran.
 */
export async function vuePlatForm({ id = null, naviguer }) {
    const [categories, plat] = await Promise.all([api.categories(), id === null ? null : api.plat(id)]);
    const enEdition = plat !== null;

    const nom = champTexte({ libelle: 'Nom du plat', nom: 'nom', valeur: plat?.nom ?? '', maxlength: NOM_MAX, requis: true });

    const idRepas = nouvelId('repas');
    const erreurRepas = messageChamp(`${idRepas}-erreur`);
    const caseDejeuner = el('input', { type: 'checkbox', id: `${idRepas}-dejeuner`, checked: plat?.convient_dejeuner ?? true });
    const caseDiner = el('input', { type: 'checkbox', id: `${idRepas}-diner`, checked: plat?.convient_diner ?? true });
    const repas = el(
        'fieldset',
        { class: 'champ', 'aria-describedby': erreurRepas.id },
        el('legend', {}, 'Repas autorisés'),
        el(
            'div',
            { class: 'choix-groupe' },
            el('div', { class: 'choix' }, caseDejeuner, el('label', { for: caseDejeuner.id }, 'Déjeuner')),
            el('div', { class: 'choix' }, caseDiner, el('label', { for: caseDiner.id }, 'Dîner')),
        ),
        erreurRepas,
    );

    const saison = groupeRadio({
        legende: 'Saison',
        nom: 'saison',
        valeur: plat?.saison,
        options: [['printemps_ete', 'Printemps-été'], ['automne_hiver', 'Automne-hiver'], ['hybride', 'Hybride']],
    });
    const type = groupeRadio({
        legende: 'Type',
        nom: 'type',
        valeur: plat?.type ?? 'classique',
        options: [['classique', 'Classique'], ['vegetarien', 'Végétarien']],
    });
    const niveau = groupeRadio({
        legende: 'Niveau',
        nom: 'niveau',
        valeur: plat?.niveau,
        options: [['rapide', 'Rapide'], ['elabore', 'Élaboré']],
    });
    const poids = groupeRadio({
        legende: 'Préférence',
        nom: 'poids',
        valeur: plat?.poids ?? 2,
        options: Object.entries(POIDS),
    });

    const idCategorie = nouvelId('categorie');
    const erreurCategorie = messageChamp(`${idCategorie}-erreur`);
    const categorie = el(
        'select',
        { id: idCategorie, name: 'categorie_principale_id', 'aria-describedby': erreurCategorie.id },
        el('option', { value: '' }, 'Choisis une catégorie'),
        categories.map((c) => el('option', { value: c.id, selected: plat?.categorie_principale_id === c.id }, capitaliser(c.libelle))),
    );

    const erreurGlobale = el('div', { class: 'zone-alerte' });
    const zonesErreur = {
        nom: nom.erreur,
        saison: saison.erreur,
        type: type.erreur,
        niveau: niveau.erreur,
        categorie_principale_id: erreurCategorie,
        poids: poids.erreur,
        repas: erreurRepas,
    };

    const effacerErreurs = () => {
        erreurGlobale.replaceChildren();
        Object.values(zonesErreur).forEach((zone) => afficherMessage(zone, ''));
    };

    /** Affiche les erreurs rattachées aux champs ; renvoie le premier champ en erreur pour y mettre le focus. */
    const afficherErreurs = (erreur) => {
        effacerErreurs();
        const champs = erreur.champs ?? {};
        const restes = [];
        let premier = null;
        for (const [cle, message] of Object.entries(champs)) {
            if (CHAMPS_AFFICHES.includes(cle)) {
                afficherMessage(zonesErreur[cle], message);
                premier ??= cle;
            } else {
                restes.push(message);
            }
        }
        if (premier === null || restes.length > 0) {
            erreurGlobale.append(bandeau([erreur.message, ...restes].join(' '), { type: 'erreur' }));
        }

        return premier;
    };

    const focusSur = (cle) => {
        const cibles = {
            nom: nom.entree,
            saison: saison.racine,
            type: type.racine,
            niveau: niveau.racine,
            categorie_principale_id: categorie,
            poids: poids.racine,
            repas: caseDejeuner,
        };
        const cible = cibles[cle];
        (cible.querySelector?.('input') ?? cible).focus();
    };

    const bouton = el('button', { type: 'submit', class: 'bouton bouton-principal' }, 'Enregistrer');

    const formulaire = el(
        'form',
        {
            class: 'formulaire',
            novalidate: true,
            onSubmit: async (evenement) => {
                evenement.preventDefault();
                effacerErreurs();
                const donnees = {
                    nom: nom.entree.value.trim(),
                    convient_dejeuner: caseDejeuner.checked,
                    convient_diner: caseDiner.checked,
                    saison: saison.valeur(),
                    type: type.valeur(),
                    niveau: niveau.valeur(),
                    categorie_principale_id: categorie.value === '' ? null : Number(categorie.value),
                    poids: Number(poids.valeur()),
                };
                try {
                    await pendantAction(bouton, () => (enEdition ? api.modifierPlat(id, donnees) : api.creerPlat(donnees)));
                } catch (e) {
                    if (!(e instanceof ApiError)) {
                        throw e;
                    }
                    const premier = afficherErreurs(e);
                    if (premier !== null) {
                        focusSur(premier);
                    }

                    return;
                }
                annoncer('Plat enregistré');
                naviguer('#/plats');
            },
        },
        nom.racine,
        repas,
        saison.racine,
        type.racine,
        niveau.racine,
        el('div', { class: 'champ' }, el('label', { for: idCategorie }, 'Catégorie principale'), categorie, erreurCategorie),
        poids.racine,
        erreurGlobale,
        el('div', { class: 'actions barre-actions' }, bouton, el('a', { class: 'bouton bouton-secondaire', href: '#/plats' }, 'Annuler')),
    );

    const noeuds = el(
        'section',
        { class: 'ecran ecran-formulaire' },
        el('a', { class: 'retour', href: '#/plats' }, icone('retour'), 'Retour'),
        el('h1', {}, enEdition ? 'Modifier le plat' : 'Ajouter un plat'),
        formulaire,
        enEdition ? zoneSuppression({ id, naviguer, erreurGlobale }) : null,
    );

    return { titre: enEdition ? 'Modifier le plat' : 'Ajouter un plat', noeuds, focus: () => nom.entree.focus() };
}

/** Suppression en deux temps : le bouton ouvre une confirmation dont le focus part sur « Annuler ». */
function zoneSuppression({ id, naviguer, erreurGlobale }) {
    const zone = el('div', { class: 'suppression' });

    const proposer = () => {
        const supprimer = el('button', { type: 'button', class: 'bouton bouton-destructif' }, 'Supprimer');
        const annuler = el('button', { type: 'button', class: 'bouton bouton-secondaire', onClick: () => {
            zone.replaceChildren(declencheur);
            declencheur.focus();
        } }, 'Annuler');
        supprimer.addEventListener('click', async () => {
            try {
                await pendantAction(supprimer, () => api.supprimerPlat(id));
            } catch (e) {
                if (!(e instanceof ApiError)) {
                    throw e;
                }
                erreurGlobale.replaceChildren(bandeau(e.message, { type: 'erreur' }));
                annuler.click();

                return;
            }
            annoncer('Plat supprimé');
            naviguer('#/plats');
        });
        zone.replaceChildren(
            el('div', { class: 'confirmation', role: 'group', 'aria-labelledby': 'confirmation-titre' },
                el('p', { id: 'confirmation-titre' }, 'Supprimer ce plat ? Cette action est définitive.'),
                el('div', { class: 'actions' }, supprimer, annuler)),
        );
        annuler.focus();
    };

    const declencheur = el('button', { type: 'button', class: 'bouton bouton-destructif', onClick: proposer }, 'Supprimer ce plat');
    zone.append(declencheur);

    return zone;
}

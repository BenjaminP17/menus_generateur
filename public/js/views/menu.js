import { api, ApiError } from '../api.js';
import { afficherMessage, messageChamp, nouvelId, pastillesDuPlat, pendantAction, bandeau, groupeRadio } from '../components.js';
import { annoncer, capitaliser, el, normaliser, remplacer } from '../dom.js';
import { icone, illustration } from '../icons.js';
import { garderMenu, lireMenu } from '../menu-store.js';

const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const REPAS = [['dejeuner', 'Déjeuner'], ['diner', 'Dîner']];
const REPAS_EN_TEXTE = { dejeuner: 'déjeuner', diner: 'dîner' };

// Champs des réglages qui ont une zone d'erreur ; le reste va dans le bandeau.
const CHAMPS_AFFICHES = ['jours_dejeuners', 'jours_diners', 'vegetarien', 'date_reference'];

/** Cases à cocher des sept jours, présentées en boutons comme les autres choix (doc/design.md §4.2). */
function groupeJours({ legende, nom, selection }) {
    const id = nouvelId(nom);
    const erreur = messageChamp(`${id}-erreur`);
    const cases = JOURS.map((jour) => {
        const idCase = `${id}-${jour}`;

        return el(
            'div',
            { class: 'choix' },
            el('input', { type: 'checkbox', id: idCase, name: nom, value: jour, checked: selection.includes(jour) }),
            el('label', { for: idCase }, capitaliser(jour)),
        );
    });
    const racine = el(
        'fieldset',
        { class: 'champ', 'aria-describedby': erreur.id },
        el('legend', {}, legende),
        el('div', { class: 'choix-groupe' }, cases),
        erreur,
    );

    return {
        racine,
        erreur,
        valeurs: () => [...racine.querySelectorAll('input:checked')].map((entree) => entree.value),
    };
}

/** Repas du menu tels que l'API les attend en entrée. */
function placements(menu) {
    return menu.repas.map((repas) => ({ jour: repas.jour, repas: repas.repas, plat_id: repas.plat.id }));
}

function ecranBaseVide() {
    return el(
        'section',
        { class: 'ecran' },
        el('h1', {}, 'Ton menu de la semaine'),
        el(
            'div',
            { class: 'vide' },
            illustration(),
            el('p', {}, 'Ta base est vide. Ajoute quelques plats pour que je puisse composer ton menu.'),
            el('a', { class: 'bouton bouton-principal', href: '#/plats' }, icone('plats'), 'Mes plats'),
        ),
    );
}

/** Pastille « À vérifier » : icône et texte, comme les autres pastilles. */
function pastilleAVerifier() {
    return el('span', { class: 'pastille pastille-verifier' }, icone('alerte'), 'À vérifier');
}

/** Écran du menu (doc/design.md §4.2). */
export async function vueMenu() {
    const plats = await api.plats();
    if (plats.length === 0) {
        return { titre: 'Menu', noeuds: ecranBaseVide() };
    }

    const parId = new Map(plats.map((plat) => [plat.id, plat]));

    // Le menu gardé peut dater d'avant une modification de plat : on reprend les plats à jour.
    let courant = lireMenu();
    if (courant !== null) {
        for (const repas of courant.menu.repas) {
            repas.plat = parId.get(repas.plat.id) ?? repas.plat;
        }
    }

    // Réglages

    const reglagesInitiaux = courant?.reglages;
    const dejeuners = groupeJours({
        legende: 'Jours de déjeuner',
        nom: 'jours_dejeuners',
        selection: reglagesInitiaux?.jours_dejeuners ?? JOURS,
    });
    const diners = groupeJours({
        legende: 'Jours de dîner',
        nom: 'jours_diners',
        selection: reglagesInitiaux?.jours_diners ?? JOURS,
    });
    const vegetarien = groupeRadio({
        legende: 'Repas végétariens',
        nom: 'vegetarien',
        valeur: reglagesInitiaux?.vegetarien ?? 'quelques_uns',
        options: [['aucun', 'Aucun'], ['quelques_uns', 'Quelques-uns'], ['majoritairement', 'Majoritairement']],
    });

    const idDate = nouvelId('date_reference');
    const erreurDate = messageChamp(`${idDate}-erreur`);
    const date = el('input', {
        id: idDate,
        name: 'date_reference',
        type: 'date',
        value: reglagesInitiaux?.date_reference ?? '',
        'aria-describedby': erreurDate.id,
    });

    const zonesErreur = {
        jours_dejeuners: dejeuners.erreur,
        jours_diners: diners.erreur,
        vegetarien: vegetarien.erreur,
        date_reference: erreurDate,
    };
    const erreurReglages = el('div', { class: 'zone-alerte' });
    const zoneErreur = el('div', { class: 'zone-alerte' });

    const effacerErreursReglages = () => {
        erreurReglages.replaceChildren();
        Object.values(zonesErreur).forEach((zone) => afficherMessage(zone, ''));
    };

    const afficherErreursReglages = (erreur) => {
        effacerErreursReglages();
        const restes = [];
        let premier = null;
        for (const [cle, message] of Object.entries(erreur.champs ?? {})) {
            if (CHAMPS_AFFICHES.includes(cle)) {
                afficherMessage(zonesErreur[cle], message);
                premier ??= cle;
            } else {
                restes.push(message);
            }
        }
        if (premier === null || restes.length > 0) {
            erreurReglages.append(bandeau([erreur.message, ...restes].join(' '), { type: 'erreur' }));
        }

        return premier;
    };

    const focusSurChamp = (cle) => {
        const cibles = { jours_dejeuners: dejeuners.racine, jours_diners: diners.racine, vegetarien: vegetarien.racine, date_reference: date };
        const cible = cibles[cle];
        (cible.querySelector?.('input') ?? cible).focus();
    };

    const lireReglages = () => ({
        jours_dejeuners: dejeuners.valeurs(),
        jours_diners: diners.valeurs(),
        vegetarien: vegetarien.valeur(),
        ...(date.value === '' ? {} : { date_reference: date.value }),
    });

    // Menu

    const zoneMenu = el('div', { class: 'zone-menu' });
    const reglages = el('details', { class: 'reglages', open: courant === null });

    /** Planning, bandeau d'avertissements et actions. `focus` : `jour:repas:action` à refocaliser, ou 'titre'. */
    const afficherMenu = (focus = null) => {
        if (courant === null) {
            zoneMenu.replaceChildren();

            return;
        }
        const { menu } = courant;
        const titre = el('h2', { id: 'titre-menu', tabindex: '-1' }, 'Ton menu');
        const toutRegenerer = el(
            'button',
            { type: 'button', class: 'bouton bouton-secondaire', 'data-focus': 'tout', onClick: regenererTout },
            'Tout régénérer',
        );

        remplacer(
            zoneMenu,
            el('div', { class: 'en-tete' }, titre, toutRegenerer),
            avertissements(menu),
            planning(courant),
        );

        if (focus === 'titre') {
            titre.focus({ preventScroll: true });
            titre.scrollIntoView({ block: 'start' });
        } else if (focus !== null) {
            zoneMenu.querySelector(`[data-focus="${focus}"]`)?.focus();
        }
    };

    /** Bandeau qui résume tous les écarts du menu. */
    const avertissements = (menu) => {
        if (menu.avertissements.length === 0) {
            return null;
        }

        return el(
            'div',
            { class: 'bandeau bandeau-liste', role: 'status' },
            icone('alerte'),
            el(
                'div',
                {},
                el('p', { class: 'bandeau-titre' }, menu.complet ? 'À vérifier' : 'Le menu est incomplet'),
                el('ul', {}, menu.avertissements.map((avertissement) => el('li', {}, avertissement.message))),
            ),
        );
    };

    const planning = ({ reglages: utilises, menu }) => {
        const jours = JOURS.filter((jour) => utilises.jours_dejeuners.includes(jour) || utilises.jours_diners.includes(jour));
        const repasDuMenu = new Map(menu.repas.map((repas) => [`${repas.jour}:${repas.repas}`, repas]));
        const aVerifier = new Set(
            menu.avertissements.filter((a) => a.jour !== undefined).map((a) => `${a.jour}:${a.repas}`),
        );
        const idsDuMenu = new Set(menu.repas.map((repas) => repas.plat.id));

        return el(
            'ol',
            { class: 'planning' },
            jours.map((jour) => el(
                'li',
                { class: 'jour' },
                el('h3', {}, capitaliser(jour)),
                el(
                    'ul',
                    { class: 'repas-liste' },
                    REPAS
                        .filter(([repas]) => utilises[repas === 'dejeuner' ? 'jours_dejeuners' : 'jours_diners'].includes(jour))
                        .map(([repas, libelle]) => carteRepas({
                            jour,
                            repas,
                            libelle,
                            entree: repasDuMenu.get(`${jour}:${repas}`) ?? null,
                            aVerifier: aVerifier.has(`${jour}:${repas}`),
                            idsDuMenu,
                        })),
                ),
            )),
        );
    };

    const carteRepas = ({ jour, repas, libelle, entree, aVerifier, idsDuMenu }) => {
        // Le texte visible du bouton reste en tête du nom accessible, la précision dit de quel repas il s'agit.
        const precision = (debut) => el('span', { class: 'sr-only' }, ` ${debut} ${REPAS_EN_TEXTE[repas]} du ${jour}`);
        const cle = `${jour}:${repas}`;

        return el(
            'li',
            { class: 'repas' },
            el('h4', { class: 'repas-titre' }, libelle),
            entree === null
                ? el('p', { class: 'repas-plat secondaire' }, 'Aucun plat disponible.')
                : el('p', { class: 'repas-plat' }, entree.plat.nom),
            entree === null && !aVerifier ? null : el(
                'div',
                { class: 'pastilles' },
                entree === null ? null : pastillesDuPlat(entree.plat),
                aVerifier ? pastilleAVerifier() : null,
            ),
            el(
                'div',
                { class: 'actions' },
                el(
                    'button',
                    { type: 'button', class: 'bouton bouton-secondaire bouton-petit', 'data-focus': `${cle}:changer`, onClick: () => regenererRepas(jour, repas) },
                    'Changer',
                    precision('le'),
                ),
                el(
                    'button',
                    { type: 'button', class: 'bouton bouton-secondaire bouton-petit', 'data-focus': `${cle}:choisir`, onClick: (evenement) => choisir(jour, repas, idsDuMenu, evenement.currentTarget) },
                    'Choisir un plat',
                    precision('pour le'),
                ),
            ),
        );
    };

    // Actions

    const occuper = (occupe) => {
        if (occupe) {
            zoneMenu.setAttribute('aria-busy', 'true');
        } else {
            zoneMenu.removeAttribute('aria-busy');
        }
        zoneMenu.querySelectorAll('button').forEach((bouton) => { bouton.disabled = occupe; });
    };

    /**
     * Lance une action du menu : le menu reçu remplace l'ancien, sinon l'erreur s'affiche au-dessus
     * du planning. Le focus revient sur `focus` après le rendu, ou sur le bouton d'origine en cas d'erreur.
     */
    const executer = async (action, annonce, focus) => {
        const actif = document.activeElement;
        zoneErreur.replaceChildren();
        occuper(true);
        let echec = false;
        try {
            const menu = await action();
            courant = { reglages: courant.reglages, menu };
            garderMenu(courant.reglages, menu);
            afficherMenu(focus);
            annoncer(annonce);
        } catch (e) {
            if (!(e instanceof ApiError)) {
                throw e;
            }
            echec = true;
            zoneErreur.append(bandeau(e.message, { type: 'erreur' }));
        } finally {
            occuper(false);
            if (echec) {
                actif?.focus?.();
            }
        }
    };

    function regenererTout() {
        return executer(() => api.genererMenu(courant.reglages), 'Menu régénéré', 'tout');
    }

    function regenererRepas(jour, repas) {
        return executer(
            () => api.regenererRepas(courant.reglages, placements(courant.menu), { jour, repas }),
            `${capitaliser(REPAS_EN_TEXTE[repas])} du ${jour} changé`,
            `${jour}:${repas}:changer`,
        );
    }

    function choisir(jour, repas, idsDuMenu, declencheur) {
        const boite = dialogueDePlats({
            titre: `Choisir un plat pour le ${REPAS_EN_TEXTE[repas]} du ${jour}`,
            plats,
            idsDuMenu,
            surChoix: (plat) => executer(
                () => api.choisirPlat(courant.reglages, placements(courant.menu), { jour, repas }, plat.id),
                `${capitaliser(REPAS_EN_TEXTE[repas])} du ${jour} : ${plat.nom}`,
                `${jour}:${repas}:choisir`,
            ),
            surAnnulation: () => declencheur.focus(),
        });
        document.body.append(boite.noeud);
        boite.noeud.showModal();
        boite.recherche.focus();
    }

    // Formulaire de réglages

    const boutonGenerer = el('button', { type: 'submit', class: 'bouton bouton-principal' }, 'Générer le menu');
    const formulaire = el(
        'form',
        {
            class: 'formulaire',
            novalidate: true,
            onSubmit: async (evenement) => {
                evenement.preventDefault();
                effacerErreursReglages();
                zoneErreur.replaceChildren();
                const demandes = lireReglages();
                try {
                    const menu = await pendantAction(boutonGenerer, () => api.genererMenu(demandes));
                    courant = { reglages: demandes, menu };
                    garderMenu(demandes, menu);
                } catch (e) {
                    if (!(e instanceof ApiError)) {
                        throw e;
                    }
                    const premier = afficherErreursReglages(e);
                    if (premier !== null) {
                        focusSurChamp(premier);
                    }

                    return;
                }
                reglages.open = false;
                afficherMenu('titre');
                annoncer('Menu généré');
            },
        },
        dejeuners.racine,
        diners.racine,
        vegetarien.racine,
        el('div', { class: 'champ' }, el('label', { for: idDate }, 'Date de référence (facultatif, par défaut aujourd\'hui)'), date, erreurDate),
        erreurReglages,
        el('div', { class: 'actions barre-actions' }, boutonGenerer),
    );
    reglages.append(el('summary', {}, 'Réglages'), formulaire);

    afficherMenu();

    return {
        titre: 'Menu',
        noeuds: el(
            'section',
            { class: 'ecran ecran-menu' },
            el('h1', {}, 'Ton menu de la semaine'),
            reglages,
            zoneErreur,
            zoneMenu,
        ),
    };
}

/**
 * Choix libre d'un plat dans un <dialog> modal : recherche par nom, liste de boutons. Le focus
 * part sur la recherche ; Échap ou « Annuler » ferment sans rien changer.
 */
function dialogueDePlats({ titre, plats, idsDuMenu, surChoix, surAnnulation }) {
    const idTitre = nouvelId('choix-titre');
    const idRecherche = nouvelId('choix-recherche');
    const liste = el('ul', { class: 'liste-cartes liste-choix' });
    const aucun = el('p', { class: 'secondaire', hidden: true }, 'Aucun plat ne correspond à ta recherche.');
    const recherche = el('input', { id: idRecherche, type: 'search', autocomplete: 'off', onInput: () => afficher() });
    const annuler = el('button', { type: 'button', class: 'bouton bouton-secondaire' }, 'Annuler');
    const noeud = el(
        'dialog',
        { class: 'confirmation-modale dialogue-plats', 'aria-labelledby': idTitre },
        el('h2', { id: idTitre }, titre),
        el('div', { class: 'champ' }, el('label', { for: idRecherche }, 'Rechercher un plat'), recherche),
        liste,
        aucun,
        el('div', { class: 'actions' }, annuler),
    );

    let choisi = false;
    const afficher = () => {
        const cherche = normaliser(recherche.value.trim());
        const visibles = plats.filter((plat) => normaliser(plat.nom).includes(cherche));
        liste.replaceChildren(...visibles.map((plat) => el(
            'li',
            {},
            el(
                'button',
                {
                    type: 'button',
                    class: 'carte carte-bouton',
                    onClick: () => {
                        choisi = true;
                        noeud.close();
                        surChoix(plat);
                    },
                },
                el('span', { class: 'carte-nom' }, plat.nom),
                pastillesDuPlat(plat),
                idsDuMenu.has(plat.id) ? el('span', { class: 'carte-poids' }, 'Déjà au menu') : null,
            ),
        )));
        aucun.hidden = visibles.length > 0;
    };
    afficher();

    annuler.addEventListener('click', () => noeud.close());
    noeud.addEventListener('close', () => {
        noeud.remove();
        if (!choisi) {
            surAnnulation();
        }
    });

    return { noeud, recherche };
}

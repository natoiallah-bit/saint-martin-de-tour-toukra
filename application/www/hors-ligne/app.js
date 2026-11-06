// Mode hors ligne : appel, notes et encaissements gardés sur l'appareil puis envoyés au serveur
(function () {
  var L = window.Logique;
  var serveur = window.AdresseServeur.lireAdresse();

  // ---------- Stockage sur l'appareil ----------
  function lire(cle, defaut) {
    try { var v = localStorage.getItem('hl_' + cle); return v === null ? defaut : JSON.parse(v); } catch (e) { return defaut; }
  }
  function ecrire(cle, valeur) { localStorage.setItem('hl_' + cle, JSON.stringify(valeur)); }

  var session = lire('session', null);   // { jeton, utilisateur, serveur }
  var donnees = lire('donnees', null);   // dernier téléchargement
  var file = lire('file', []);           // saisies faites ici
  var onglet = lire('onglet', null);

  function sauverFile() { ecrire('file', file); afficherCompteurs(); }

  // ---------- Petits outils d'affichage ----------
  function $(id) { return document.getElementById(id); }
  function el(balise, attributs, enfants) {
    var n = document.createElement(balise);
    Object.keys(attributs || {}).forEach(function (k) {
      if (k === 'texte') n.textContent = attributs[k];
      else if (k === 'classe') n.className = attributs[k];
      else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2), attributs[k]);
      else n.setAttribute(k, attributs[k]);
    });
    (enfants || []).forEach(function (e) { if (e) n.appendChild(typeof e === 'string' ? document.createTextNode(e) : e); });
    return n;
  }
  function vider(n) { while (n.firstChild) n.removeChild(n.firstChild); return n; }
  function options(select, liste, valeur) {
    vider(select);
    liste.forEach(function (o) { select.appendChild(el('option', { value: o.valeur, texte: o.texte })); });
    if (valeur !== undefined && liste.some(function (o) { return String(o.valeur) === String(valeur); })) select.value = valeur;
  }
  var minuterieMessage = null;
  function message(texte, type) {
    var m = $('message');
    m.textContent = texte;
    m.className = 'message ' + (type || '');
    m.hidden = false;
    clearTimeout(minuterieMessage);
    minuterieMessage = setTimeout(function () { m.hidden = true; }, 4000);
  }
  function dateLisible(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    return d.toLocaleDateString('fr-FR') + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }
  function nomEleve(e) { return e.nom + ' ' + e.prenom; }
  function classe(id) { return (donnees.classes || []).find(function (c) { return c.id === id; }); }
  function elevesDe(classeId) { return (donnees.eleves || []).filter(function (e) { return e.classe_id === classeId; }); }

  // ---------- Serveur ----------
  function appeler(chemin, corps) {
    var controle = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var minuterie = setTimeout(function () { if (controle) controle.abort(); }, 30000);
    var entetes = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (session && session.jeton) entetes.Authorization = 'Bearer ' + session.jeton;

    return fetch(serveur + '/api' + chemin, {
      method: corps === undefined ? 'GET' : 'POST',
      headers: entetes,
      body: corps === undefined ? undefined : JSON.stringify(corps),
      signal: controle ? controle.signal : undefined,
    }).then(function (r) {
      clearTimeout(minuterie);
      return r.json().catch(function () { return null; }).then(function (json) {
        if (r.status === 401 && session) {
          session = null; ecrire('session', null);
          afficher();
          throw new Error('Session expirée : reconnectez cet appareil.');
        }
        if (r.status === 422) {
          var erreurs = json && json.errors ? Object.keys(json.errors).map(function (k) { return json.errors[k].join(' '); }) : [];
          throw new Error(erreurs.join(' ') || (json && json.message) || 'Données refusées.');
        }
        if (!r.ok || json === null) throw new Error((json && json.message) || 'Le serveur a répondu par une erreur (' + r.status + ').');
        return json;
      });
    }, function () {
      clearTimeout(minuterie);
      var e = new Error('Pas de connexion avec le serveur de l\'école.');
      e.reseau = true;
      throw e;
    });
  }

  function telecharger() {
    return appeler('/donnees').then(function (d) {
      d.telecharge_le = new Date().toISOString();
      donnees = d;
      ecrire('donnees', d);
      afficherReseau(true);
      afficher();
      return d;
    });
  }

  var envoiEnCours = false;
  function envoyer(silencieux) {
    var aEnvoyer = file.filter(function (e) { return e.statut === 'attente'; });
    if (envoiEnCours || !session) return Promise.resolve();
    if (!aEnvoyer.length) {
      if (!silencieux) message('Rien à envoyer : tout est déjà au serveur.');
      return Promise.resolve();
    }
    envoiEnCours = true;
    var paquet = aEnvoyer.slice(0, 50).map(function (e) { return { uuid: e.uuid, type: e.type, donnees: e.donnees }; });

    return appeler('/envoyer', { elements: paquet }).then(function (r) {
      file = L.appliquerResultats(file, r.resultats, new Date().toISOString());
      sauverFile();
      afficherReseau(true);
      var refus = r.resultats.filter(function (x) { return x.statut === 'refuse'; }).length;
      message(refus ? refus + ' saisie(s) refusée(s) : voir l\'onglet Envois.' : 'Saisies envoyées au serveur.', refus ? 'alerte' : 'ok');
      envoiEnCours = false;
      // La suite s'il en reste, puis les données à jour (montants versés, notes)
      if (file.some(function (e) { return e.statut === 'attente'; })) return envoyer(true);
      return telecharger().catch(function () {});
    }).catch(function (e) {
      envoiEnCours = false;
      if (e.reseau) afficherReseau(false);
      if (!silencieux || !e.reseau) message(e.message, 'alerte');
    });
  }

  function ajouter(type, libelle, contenu) {
    var element = { uuid: L.uuid(), type: type, libelle: libelle, donnees: contenu, statut: 'attente', cree_le: new Date().toISOString() };
    file.push(element);
    sauverFile();
    envoyer(true);
    return element;
  }

  // ---------- En-tête et état ----------
  function afficherReseau(enLigne) {
    var p = $('etat-reseau');
    p.textContent = enLigne ? 'Connecté' : 'Hors ligne';
    p.className = 'pastille ' + (enLigne ? 'ok' : 'hors');
  }

  function afficherCompteurs() {
    var attente = file.filter(function (e) { return e.statut === 'attente'; }).length;
    var refus = file.filter(function (e) { return e.statut === 'refuse'; }).length;
    $('btn-envoyer').textContent = attente ? 'Envoyer (' + attente + ')' : 'Envoyer';
    var o = document.querySelector('[data-onglet="envois"]');
    if (o) o.textContent = 'Envois' + (attente ? ' (' + attente + ')' : '') + (refus ? ' ⚠' : '');
    if (onglet === 'envois') afficherEnvois();
  }

  // ---------- Écrans ----------
  function afficher() {
    $('adresse-serveur').textContent = serveur || '(aucune adresse : revenez à l\'accueil)';
    if (!session) {
      $('ecran-connexion').hidden = false;
      $('ecran-travail').hidden = true;
      $('entete-utilisateur').textContent = '';
      return;
    }
    $('ecran-connexion').hidden = true;
    $('ecran-travail').hidden = false;
    $('entete-utilisateur').textContent = session.utilisateur.nom + (donnees ? ' · ' + donnees.annee_scolaire : '');

    if (!donnees) {
      $('info-donnees').textContent = 'Aucune donnée sur cet appareil : touchez « Mettre à jour les données » avec le réseau.';
      vider($('onglets'));
      ['appel', 'notes', 'paiement', 'envois'].forEach(function (o) { $('onglet-' + o).hidden = true; });
      return;
    }
    $('info-donnees').textContent = 'Données du ' + dateLisible(donnees.telecharge_le) + ' · ' + (donnees.eleves || []).length + ' élèves';

    var disponibles = [];
    if (donnees.droits.appel) disponibles.push(['appel', 'Appel']);
    if (donnees.droits.notes) disponibles.push(['notes', 'Notes']);
    if (donnees.droits.paiement) disponibles.push(['paiement', 'Encaissement']);
    disponibles.push(['envois', 'Envois']);
    if (!disponibles.some(function (o) { return o[0] === onglet; })) onglet = disponibles[0][0];

    var nav = vider($('onglets'));
    disponibles.forEach(function (o) {
      nav.appendChild(el('button', {
        type: 'button', 'data-onglet': o[0], classe: o[0] === onglet ? 'actif' : '', texte: o[1],
        onclick: function () { onglet = o[0]; ecrire('onglet', onglet); afficher(); },
      }));
    });
    ['appel', 'notes', 'paiement', 'envois'].forEach(function (o) { $('onglet-' + o).hidden = o !== onglet; });
    afficherCompteurs();

    if (onglet === 'appel') preparerAppel();
    if (onglet === 'notes') preparerNotes();
    if (onglet === 'paiement') preparerPaiement();
  }

  // ---------- Appel ----------
  var statutsAppel = {};

  function preparerAppel() {
    var classes = (donnees.classes || []).filter(function (c) { return donnees.appel.classe_ids.indexOf(c.id) !== -1; });
    options($('appel-classe'), classes.map(function (c) { return { valeur: c.id, texte: c.nom }; }), $('appel-classe').value || lire('appel_classe'));
    if (!$('appel-date').value) $('appel-date').value = L.aujourdhui();
    preparerCours();
  }

  function preparerCours() {
    var classeId = Number($('appel-classe').value);
    var date = $('appel-date').value;
    var choix = L.creneauxDuJour(donnees, classeId, date).map(function (c) {
      return { valeur: c.id, texte: c.heure_debut + '–' + c.heure_fin + ' · ' + (c.matiere || 'Cours') };
    });
    if (donnees.appel.appel_journee) choix.push({ valeur: '', texte: 'Toute la journée' });
    options($('appel-cours'), choix, $('appel-cours').value);
    $('appel-enregistrer').disabled = !choix.length;
    statutsAppel = {};
    afficherAppel(choix.length ? null : 'Pas de cours pour vous dans cette classe ce jour-là.');
  }

  function afficherAppel(avertissement) {
    var liste = vider($('appel-liste'));
    if (avertissement) { liste.appendChild(el('p', { classe: 'aide', texte: avertissement })); return; }
    var eleves = elevesDe(Number($('appel-classe').value));
    if (!eleves.length) { liste.appendChild(el('p', { classe: 'aide', texte: 'Aucun élève dans cette classe.' })); return; }

    var compte = { present: 0, absent: 0, retard: 0 };
    eleves.forEach(function (e) { compte[statutsAppel[e.id] || 'present']++; });
    liste.appendChild(el('p', { classe: 'aide', texte: eleves.length + ' élèves · ' + compte.absent + ' absent(s) · ' + compte.retard + ' retard(s). Touchez un statut pour le changer.' }));

    eleves.forEach(function (e) {
      var statut = statutsAppel[e.id] || 'present';
      var boutons = [['present', 'P'], ['absent', 'A'], ['retard', 'R']].map(function (s) {
        return el('button', {
          type: 'button', classe: 'statut ' + s[0] + (statut === s[0] ? ' choisi' : ''), texte: s[1], title: s[0],
          onclick: function () { statutsAppel[e.id] = s[0]; afficherAppel(); },
        });
      });
      liste.appendChild(el('div', { classe: 'eleve' }, [el('span', { texte: nomEleve(e) }), el('span', { classe: 'choix' }, boutons)]));
    });
  }

  function enregistrerAppel() {
    var classeId = Number($('appel-classe').value);
    var eleves = elevesDe(classeId);
    if (!eleves.length) return;
    var statuts = {};
    eleves.forEach(function (e) { statuts[e.id] = statutsAppel[e.id] || 'present'; });
    var cours = $('appel-cours').value;
    var libelleCours = $('appel-cours').options[$('appel-cours').selectedIndex].text;
    var date = $('appel-date').value;

    ajouter('appel', 'Appel ' + classe(classeId).nom + ' · ' + date.split('-').reverse().join('/') + ' · ' + libelleCours, {
      classe_id: classeId, date: date, cours: cours ? Number(cours) : null, statuts: statuts,
    });
    ecrire('appel_classe', classeId);
    statutsAppel = {};
    afficherAppel();
    message('Appel enregistré sur l\'appareil.', 'ok');
  }

  // ---------- Notes ----------
  function preparerNotes() {
    var n = donnees.notes;
    var classeIds = n.paires.map(function (p) { return p.classe_id; });
    var classes = (donnees.classes || []).filter(function (c) { return classeIds.indexOf(c.id) !== -1; });
    options($('notes-classe'), classes.map(function (c) { return { valeur: c.id, texte: c.nom }; }), $('notes-classe').value);
    preparerMatieres();
  }

  function preparerMatieres() {
    var classeId = Number($('notes-classe').value);
    var ids = donnees.notes.paires.filter(function (p) { return p.classe_id === classeId; }).map(function (p) { return p.matiere_id; });
    var matieres = donnees.notes.matieres.filter(function (m) { return ids.indexOf(m.id) !== -1; });
    options($('notes-matiere'), matieres.map(function (m) { return { valeur: m.id, texte: m.nom }; }), $('notes-matiere').value);
    afficherNotes();
  }

  function contexteNotes() {
    return { classeId: Number($('notes-classe').value), matiereId: Number($('notes-matiere').value), trimestre: Number($('notes-trimestre').value) };
  }

  function afficherNotes() {
    var zone = vider($('notes-grille'));
    var c = contexteNotes();
    if (!c.classeId || !c.matiereId) { zone.appendChild(el('p', { classe: 'aide', texte: 'Aucune classe ou matière disponible.' })); return; }
    var g = L.grilleNotes(donnees, file, c.classeId, c.matiereId, c.trimestre);
    if (!g.evaluations.length) {
      zone.appendChild(el('p', { classe: 'aide', texte: 'Aucun devoir pour ce trimestre : seule la composition peut être saisie ici. Les devoirs (libellé et pourcentage) se créent sur le PC.' }));
    }

    var entete = [el('th', { texte: 'Élève' })];
    g.evaluations.forEach(function (e) { entete.push(el('th', { texte: e.libelle + ' (' + e.poids + ' %)' })); });
    entete.push(el('th', { texte: 'Compo.' }));

    var corps = elevesDe(c.classeId).map(function (eleve) {
      var v = g.valeurs[eleve.id] || { devoirs: {}, moyenne_composition: null };
      var cellules = [el('td', { texte: nomEleve(eleve) })];
      g.evaluations.forEach(function (e) {
        var note = v.devoirs[e.id];
        cellules.push(el('td', {}, [el('input', {
          type: 'number', min: '0', max: '20', step: '0.25', inputmode: 'decimal',
          'data-eleve': eleve.id, 'data-devoir': e.id, value: note === null || note === undefined ? '' : note,
        })]));
      });
      var compo = v.moyenne_composition;
      cellules.push(el('td', {}, [el('input', {
        type: 'number', min: '0', max: '20', step: '0.25', inputmode: 'decimal',
        'data-eleve': eleve.id, 'data-compo': '1', value: compo === null || compo === undefined ? '' : compo,
      })]));
      return el('tr', {}, cellules);
    });

    zone.appendChild(el('div', { classe: 'defilement' }, [el('table', { classe: 'notes' }, [el('thead', {}, [el('tr', {}, entete)]), el('tbody', {}, corps)])]));
  }

  function enregistrerNotes() {
    var c = contexteNotes();
    if (!c.classeId || !c.matiereId) return;
    var notes = {};
    var erreur = null;
    // Toute la grille est envoyée, comme sur la page web (une case vidée efface la note)
    elevesDe(c.classeId).forEach(function (e) { notes[e.id] = { devoirs: {}, moyenne_composition: '' }; });
    document.querySelectorAll('#notes-grille input').forEach(function (champ) {
      var valeur = champ.value.trim().replace(',', '.');
      if (valeur !== '' && (isNaN(Number(valeur)) || Number(valeur) < 0 || Number(valeur) > 20)) erreur = 'Les notes vont de 0 à 20.';
      var ligne = notes[champ.getAttribute('data-eleve')];
      if (champ.hasAttribute('data-compo')) ligne.moyenne_composition = valeur === '' ? '' : Number(valeur);
      else ligne.devoirs[champ.getAttribute('data-devoir')] = valeur === '' ? '' : Number(valeur);
    });
    if (erreur) { message(erreur, 'alerte'); return; }

    var matiere = donnees.notes.matieres.find(function (m) { return m.id === c.matiereId; });
    ajouter('notes', 'Notes ' + classe(c.classeId).nom + ' · ' + (matiere ? matiere.nom : '') + ' · T' + c.trimestre, {
      classe_id: c.classeId, matiere_id: c.matiereId, trimestre: c.trimestre, notes: notes,
    });
    message('Notes enregistrées sur l\'appareil.', 'ok');
  }

  // ---------- Encaissement ----------
  var eleveChoisi = null;

  function preparerPaiement() {
    var enc = donnees.encaissement;
    options($('paiement-mode'), Object.keys(enc.modes).map(function (k) { return { valeur: k, texte: enc.modes[k] }; }), $('paiement-mode').value);
    var cases = vider($('paiement-types'));
    Object.keys(enc.types).forEach(function (k) {
      cases.appendChild(el('label', { classe: 'case' }, [el('input', { type: 'checkbox', value: k }), ' ' + enc.types[k]]));
    });
    if (!$('paiement-date').value) $('paiement-date').value = L.aujourdhui();
    if (eleveChoisi) choisirEleve(eleveChoisi);
  }

  function rechercher() {
    var zone = vider($('paiement-resultats'));
    var eleves = L.rechercherEleves(donnees, $('paiement-recherche').value).filter(function (e) {
      return donnees.encaissement.classe_ids.indexOf(e.classe_id) !== -1;
    });
    eleves.forEach(function (e) {
      var c = classe(e.classe_id);
      zone.appendChild(el('button', {
        type: 'button', classe: 'resultat',
        texte: nomEleve(e) + ' · ' + (c ? c.nom : '') + (e.matricule ? ' · ' + e.matricule : ''),
        onclick: function () { choisirEleve(e); },
      }));
    });
  }

  function choisirEleve(e) {
    eleveChoisi = e;
    vider($('paiement-resultats'));
    $('paiement-recherche').value = '';
    $('paiement-recu').hidden = true;
    var s = L.situationEleve(donnees, file, e);
    var c = classe(e.classe_id);
    var fiche = vider($('paiement-eleve'));
    fiche.appendChild(el('strong', { texte: nomEleve(e) }));
    fiche.appendChild(el('div', { texte: (c ? c.nom : '') + (e.matricule ? ' · ' + e.matricule : '') }));
    if (s.exonere) fiche.appendChild(el('div', { classe: 'alerte', texte: 'Élève exonéré.' }));
    else if (s.sans_frais) fiche.appendChild(el('div', { classe: 'alerte', texte: 'Frais de l\'année non définis pour ce cycle.' }));
    else {
      fiche.appendChild(el('div', { texte: 'Frais annuels : ' + L.montant(s.annuel) + ' · Versé : ' + L.montant(s.verse) + (s.verse_ici ? ' (dont ' + L.montant(s.verse_ici) + ' encaissé ici)' : '') }));
      fiche.appendChild(el('div', { classe: 'reste', texte: 'Reste à payer : ' + L.montant(s.reste) }));
    }
    $('erreur-paiement').textContent = '';
    $('paiement-formulaire').hidden = false;
  }

  function encaisser(ev) {
    ev.preventDefault();
    if (!eleveChoisi) return;
    var types = Array.prototype.map.call(document.querySelectorAll('#paiement-types input:checked'), function (c) { return c.value; });
    var montant = Math.round(Number($('paiement-montant').value));
    if (!types.length) { $('erreur-paiement').textContent = 'Cochez l\'objet du paiement.'; return; }
    if (!(montant >= 1)) { $('erreur-paiement').textContent = 'Indiquez le montant.'; return; }

    var s = L.situationEleve(donnees, file, eleveChoisi);
    if (!s.exonere && !s.sans_frais && montant > s.reste
        && !window.confirm('Ce montant dépasse le reste à payer (' + L.montant(s.reste) + '). Encaisser quand même ?')) return;

    var numero = (lire('compteur', 0) || 0) + 1;
    ecrire('compteur', numero);
    var provisoire = 'PROV-' + String(numero).padStart(4, '0');
    var eleve = eleveChoisi;
    var element = ajouter('paiement', 'Encaissement ' + nomEleve(eleve) + ' · ' + L.montant(montant) + ' · ' + provisoire, {
      eleve_id: eleve.id, types: types, montant: montant, mode_paiement: $('paiement-mode').value,
      date_paiement: $('paiement-date').value, description: $('paiement-description').value.trim(),
    });
    element.provisoire = provisoire;
    sauverFile();

    var recu = vider($('paiement-recu'));
    recu.appendChild(el('strong', { texte: 'Reçu provisoire ' + provisoire }));
    recu.appendChild(el('div', { texte: nomEleve(eleve) + ' · ' + L.montant(montant) + ' · ' + $('paiement-mode').options[$('paiement-mode').selectedIndex].text }));
    recu.appendChild(el('div', { classe: 'aide', texte: 'Le numéro de reçu définitif (CS-…) est donné par le serveur à l\'envoi : voir l\'onglet Envois. Le reçu officiel s\'imprime depuis le PC.' }));
    recu.hidden = false;

    $('paiement-formulaire').reset();
    $('paiement-date').value = L.aujourdhui();
    $('paiement-formulaire').hidden = true;
    eleveChoisi = null;
  }

  // ---------- Envois ----------
  function afficherEnvois() {
    var zone = vider($('envois-liste'));
    if (!file.length) { zone.appendChild(el('p', { classe: 'aide', texte: 'Aucune saisie faite sur cet appareil.' })); return; }
    file.slice().reverse().forEach(function (e) {
      var etat = { attente: 'En attente', accepte: 'Enregistré', refuse: 'Refusé' }[e.statut];
      var details = [el('div', { texte: e.libelle }), el('div', { classe: 'aide', texte: 'Saisi le ' + dateLisible(e.cree_le) })];
      if (e.reference) details.push(el('div', { classe: 'ok', texte: 'Reçu n° ' + e.reference }));
      if (e.raison) details.push(el('div', { classe: 'alerte', texte: e.raison }));
      var actions = [el('span', { classe: 'etat ' + e.statut, texte: etat })];
      if (e.statut === 'refuse') {
        actions.push(el('button', {
          type: 'button', classe: 'petit secondaire', texte: 'Retirer',
          onclick: function () {
            if (!window.confirm('Retirer cette saisie refusée ? Elle ne sera pas enregistrée.')) return;
            file = file.filter(function (x) { return x.uuid !== e.uuid; });
            sauverFile();
          },
        }));
      }
      zone.appendChild(el('div', { classe: 'envoi' }, [el('div', {}, details), el('div', { classe: 'actions' }, actions)]));
    });
  }

  // ---------- Démarrage ----------
  $('ecran-connexion').addEventListener('submit', function (ev) {
    ev.preventDefault();
    $('erreur-connexion').textContent = '';
    if (!serveur) { $('erreur-connexion').textContent = 'Indiquez d\'abord l\'adresse du serveur sur l\'écran d\'accueil.'; return; }
    // Les saisies pas encore envoyées partent avec le compte qui les a faites, jamais un autre
    var email = $('email').value.trim();
    var compte = lire('compte', null);
    var attente = file.filter(function (e) { return e.statut === 'attente'; }).length;
    if (attente && compte && compte.toLowerCase() !== email.toLowerCase()) {
      $('erreur-connexion').textContent = attente + ' saisie(s) de ' + compte + ' ne sont pas encore envoyées : reconnectez-vous avec ce compte pour les envoyer.';
      return;
    }
    var appareil = lire('appareil_uuid', null) || L.uuid();
    ecrire('appareil_uuid', appareil);
    var nomAppareil = $('nom-appareil').value.trim();
    ecrire('nom_appareil', nomAppareil);

    appeler('/connexion', {
      email: email, password: $('mot-de-passe').value,
      appareil_uuid: appareil, appareil_nom: nomAppareil,
    }).then(function (r) {
      // Autre compte que le précédent : on repart de données vides
      if (compte && compte.toLowerCase() !== r.utilisateur.email.toLowerCase()) {
        donnees = null; ecrire('donnees', null);
        file = file.filter(function (e) { return e.statut === 'attente'; }); sauverFile();
      }
      ecrire('compte', r.utilisateur.email);
      session = { jeton: r.jeton, utilisateur: r.utilisateur, serveur: serveur };
      ecrire('session', session);
      $('mot-de-passe').value = '';
      afficher();
      return telecharger().then(function () { message('Appareil connecté, données téléchargées.', 'ok'); });
    }).catch(function (e) { $('erreur-connexion').textContent = e.message; });
  });

  $('btn-telecharger').onclick = function () {
    // Les saisies en attente partent d'abord, pour que les montants et notes soient à jour
    envoyer(true).then(function () { return telecharger(); })
      .then(function () { message('Données mises à jour.', 'ok'); })
      .catch(function (e) { if (e.reseau) afficherReseau(false); message(e.message, 'alerte'); });
  };
  $('btn-envoyer').onclick = function () { envoyer(false); };
  $('appel-classe').onchange = preparerCours;
  $('appel-date').onchange = preparerCours;
  $('appel-cours').onchange = function () { statutsAppel = {}; afficherAppel(); };
  $('appel-enregistrer').onclick = enregistrerAppel;
  $('notes-classe').onchange = preparerMatieres;
  $('notes-matiere').onchange = afficherNotes;
  $('notes-trimestre').onchange = afficherNotes;
  $('notes-enregistrer').onclick = enregistrerNotes;
  $('paiement-recherche').oninput = rechercher;
  $('paiement-formulaire').onsubmit = encaisser;
  $('envois-nettoyer').onclick = function () {
    file = file.filter(function (e) { return e.statut !== 'accepte'; });
    sauverFile();
  };
  $('lien-deconnexion').onclick = function (ev) {
    ev.preventDefault();
    var attente = file.filter(function (e) { return e.statut === 'attente'; }).length;
    if (attente && !window.confirm(attente + ' saisie(s) pas encore envoyée(s) seront perdues. Déconnecter quand même ?')) return;
    if (!attente && !window.confirm('Déconnecter cet appareil ?')) return;
    appeler('/deconnexion', {}).catch(function () {});
    session = null; donnees = null; file = [];
    ecrire('session', null); ecrire('donnees', null); ecrire('file', []); ecrire('compte', null);
    afficher();
  };
  $('nom-appareil').value = lire('nom_appareil', '') || '';

  window.addEventListener('online', function () { envoyer(true); });
  window.addEventListener('offline', function () { afficherReseau(false); });
  setInterval(function () { if (file.some(function (e) { return e.statut === 'attente'; })) envoyer(true); }, 60000);

  afficherReseau(navigator.onLine !== false);
  afficher();
  if (session) envoyer(true);
})();

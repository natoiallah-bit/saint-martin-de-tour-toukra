// Calculs du mode hors ligne, sans affichage (testés avec node --test)
(function (racine, fabrique) {
  if (typeof module === 'object' && module.exports) module.exports = fabrique();
  else racine.Logique = fabrique();
})(typeof self !== 'undefined' ? self : this, function () {
  var JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

  // Même règle que le serveur : lundi à samedi, rien le dimanche
  function jourDe(date) {
    var p = String(date).split('-');
    var d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
    return JOURS[(d.getDay() + 6) % 7] || null;
  }

  function aujourdhui(maintenant) {
    var d = maintenant || new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  function creneauxDuJour(donnees, classeId, date) {
    var jour = jourDe(date);
    return ((donnees.appel && donnees.appel.creneaux) || []).filter(function (c) {
      return c.classe_id === classeId && c.jour === jour;
    });
  }

  // Saisies qui ne sont pas encore dans les données téléchargées :
  // en attente d'envoi, ou acceptées après le dernier téléchargement
  function saisiesNonComprises(file, type, dateDonnees) {
    return file.filter(function (e) {
      if (e.type !== type || e.statut === 'refuse') return false;
      return e.statut === 'attente' || !dateDonnees || e.envoye_le > dateDonnees;
    });
  }

  // Grille de notes : notes téléchargées, puis saisies faites ici (la plus récente gagne)
  function grilleNotes(donnees, file, classeId, matiereId, trimestre) {
    var n = donnees.notes || {};
    var evaluations = (n.evaluations || []).filter(function (e) {
      return e.classe_id === classeId && e.matiere_id === matiereId && e.trimestre === trimestre;
    });
    var ids = evaluations.map(function (e) { return e.id; });
    var valeurs = {};
    function cellule(eleveId) { return (valeurs[eleveId] = valeurs[eleveId] || { devoirs: {}, moyenne_composition: null }); }

    (n.notes_devoirs || []).forEach(function (x) {
      if (ids.indexOf(x.evaluation_id) !== -1) cellule(x.eleve_id).devoirs[x.evaluation_id] = x.note;
    });
    (n.compositions || []).forEach(function (x) {
      if (x.matiere_id === matiereId && x.trimestre === trimestre) cellule(x.eleve_id).moyenne_composition = x.note;
    });
    saisiesNonComprises(file, 'notes', donnees.telecharge_le).forEach(function (e) {
      var d = e.donnees;
      if (d.classe_id !== classeId || d.matiere_id !== matiereId || d.trimestre !== trimestre) return;
      Object.keys(d.notes || {}).forEach(function (eleveId) {
        var v = d.notes[eleveId];
        var c = cellule(eleveId);
        Object.keys(v.devoirs || {}).forEach(function (id) { c.devoirs[id] = v.devoirs[id] === '' ? null : v.devoirs[id]; });
        if ('moyenne_composition' in v) c.moyenne_composition = v.moyenne_composition === '' ? null : v.moyenne_composition;
      });
    });

    return { evaluations: evaluations, valeurs: valeurs };
  }

  // Moyenne des devoirs pondérée par leur pourcentage (devoirs non notés ignorés), comme le serveur
  function moyennePonderee(evaluations, notes) {
    var total = 0;
    var poids = 0;
    evaluations.forEach(function (e) {
      var note = notes[e.id];
      if (note === null || note === undefined || note === '') return;
      total += Number(note) * e.poids;
      poids += e.poids;
    });
    return poids ? Math.round((total / poids) * 100) / 100 : null;
  }

  // Situation d'un élève : frais de l'année, déjà versé (serveur + encaissé ici), reste à payer
  function situationEleve(donnees, file, eleve) {
    var enc = donnees.encaissement || {};
    var classe = (donnees.classes || []).find(function (c) { return c.id === eleve.classe_id; });
    var frais = classe && enc.frais ? enc.frais[classe.cycle_id] : null;
    var annuel = frais ? frais.montant_annuel : 0;
    var verseServeur = (enc.verses && enc.verses[eleve.id]) || 0;
    var verseIci = saisiesNonComprises(file, 'paiement', donnees.telecharge_le)
      .filter(function (e) { return e.donnees.eleve_id === eleve.id; })
      .reduce(function (s, e) { return s + Number(e.donnees.montant); }, 0);
    var verse = verseServeur + verseIci;

    return {
      annuel: annuel,
      verse: verse,
      verse_ici: verseIci,
      reste: eleve.exonere ? 0 : Math.max(annuel - verse, 0),
      exonere: !!eleve.exonere,
      sans_frais: !frais,
    };
  }

  // Applique la réponse du serveur aux saisies envoyées
  function appliquerResultats(file, resultats, maintenant) {
    var parUuid = {};
    resultats.forEach(function (r) { parUuid[r.uuid] = r; });
    return file.map(function (e) {
      var r = parUuid[e.uuid];
      if (!r) return e;
      var copie = Object.assign({}, e, { statut: r.statut, envoye_le: maintenant });
      if (r.reference) copie.reference = r.reference;
      if (r.raison) copie.raison = r.raison;
      return copie;
    });
  }

  function rechercherEleves(donnees, texte) {
    var t = String(texte || '').trim().toLowerCase();
    if (t.length < 2) return [];
    return (donnees.eleves || []).filter(function (e) {
      return (e.nom + ' ' + e.prenom + ' ' + e.prenom + ' ' + e.nom + ' ' + (e.matricule || '')).toLowerCase().indexOf(t) !== -1;
    }).slice(0, 30);
  }

  function montant(n) {
    return Math.round(Number(n) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
  }

  return {
    jourDe: jourDe,
    aujourdhui: aujourdhui,
    uuid: uuid,
    creneauxDuJour: creneauxDuJour,
    grilleNotes: grilleNotes,
    moyennePonderee: moyennePonderee,
    situationEleve: situationEleve,
    appliquerResultats: appliquerResultats,
    rechercherEleves: rechercherEleves,
    montant: montant,
  };
});

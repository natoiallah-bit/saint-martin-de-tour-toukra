const test = require('node:test');
const assert = require('node:assert');
const L = require('../www/hors-ligne/logique.js');

const donnees = {
  telecharge_le: '2026-10-05T08:00:00.000Z',
  classes: [{ id: 1, nom: 'CM2', cycle_id: 7 }],
  eleves: [{ id: 10, classe_id: 1, nom: 'ABAKAR', prenom: 'Aïcha', matricule: 'CS-001', exonere: false }],
  appel: { creneaux: [
    { id: 3, classe_id: 1, jour: 'lundi', heure_debut: '07:00', heure_fin: '09:00', matiere: 'Maths' },
    { id: 4, classe_id: 1, jour: 'mardi', heure_debut: '07:00', heure_fin: '09:00', matiere: 'Français' },
  ] },
  notes: {
    evaluations: [{ id: 5, classe_id: 1, matiere_id: 2, trimestre: 1, libelle: 'D1', poids: 40 }, { id: 6, classe_id: 1, matiere_id: 2, trimestre: 1, libelle: 'D2', poids: 60 }],
    notes_devoirs: [{ evaluation_id: 5, eleve_id: 10, note: 10 }],
    compositions: [{ eleve_id: 10, matiere_id: 2, trimestre: 1, note: 9 }],
  },
  encaissement: { frais: { 7: { montant_annuel: 100000 } }, verses: { 10: 30000 } },
};

test('jour de la semaine comme le serveur (lundi à samedi, rien le dimanche)', () => {
  assert.strictEqual(L.jourDe('2026-10-05'), 'lundi');
  assert.strictEqual(L.jourDe('2026-10-10'), 'samedi');
  assert.strictEqual(L.jourDe('2026-10-11'), null);
  assert.deepStrictEqual(L.creneauxDuJour(donnees, 1, '2026-10-06').map((c) => c.id), [4]);
});

test('grille de notes : serveur puis saisies faites ici', () => {
  const file = [
    { type: 'notes', statut: 'attente', donnees: { classe_id: 1, matiere_id: 2, trimestre: 1, notes: { 10: { devoirs: { 6: 15 }, moyenne_composition: '' } } } },
    { type: 'notes', statut: 'refuse', donnees: { classe_id: 1, matiere_id: 2, trimestre: 1, notes: { 10: { devoirs: { 5: 0 } } } } },
  ];
  const g = L.grilleNotes(donnees, file, 1, 2, 1);
  assert.strictEqual(g.evaluations.length, 2);
  assert.strictEqual(g.valeurs[10].devoirs[5], 10);
  assert.strictEqual(g.valeurs[10].devoirs[6], 15);
  assert.strictEqual(g.valeurs[10].moyenne_composition, null);
  assert.strictEqual(L.moyennePonderee(g.evaluations, g.valeurs[10].devoirs), 13);
  assert.strictEqual(L.moyennePonderee(g.evaluations, {}), null);
});

test('reste à payer : versé au serveur + encaissé ici non encore compris', () => {
  const file = [
    { type: 'paiement', statut: 'attente', donnees: { eleve_id: 10, montant: 20000 } },
    { type: 'paiement', statut: 'accepte', envoye_le: '2026-10-05T07:00:00.000Z', donnees: { eleve_id: 10, montant: 5000 } },
    { type: 'paiement', statut: 'accepte', envoye_le: '2026-10-05T09:00:00.000Z', donnees: { eleve_id: 10, montant: 1000 } },
    { type: 'paiement', statut: 'refuse', donnees: { eleve_id: 10, montant: 99999 } },
  ];
  const s = L.situationEleve(donnees, file, donnees.eleves[0]);
  assert.strictEqual(s.verse, 51000);
  assert.strictEqual(s.reste, 49000);
  assert.strictEqual(L.situationEleve(donnees, [], { ...donnees.eleves[0], exonere: true }).reste, 0);
});

test('résultats du serveur : statut, numéro de reçu, raison', () => {
  const file = [{ uuid: 'a', statut: 'attente' }, { uuid: 'b', statut: 'attente' }, { uuid: 'c', statut: 'attente' }];
  const r = L.appliquerResultats(file, [
    { uuid: 'a', statut: 'accepte', reference: 'CS-2026-0007' },
    { uuid: 'b', statut: 'refuse', raison: 'Hors de votre cycle.' },
  ], 'T');
  assert.deepStrictEqual(r.map((e) => e.statut), ['accepte', 'refuse', 'attente']);
  assert.strictEqual(r[0].reference, 'CS-2026-0007');
  assert.strictEqual(r[1].raison, 'Hors de votre cycle.');
  assert.strictEqual(r[0].envoye_le, 'T');
});

test('recherche d\'élève par nom, prénom ou matricule', () => {
  assert.strictEqual(L.rechercherEleves(donnees, 'aïcha abakar').length, 1);
  assert.strictEqual(L.rechercherEleves(donnees, 'cs-001').length, 1);
  assert.strictEqual(L.rechercherEleves(donnees, 'a').length, 0);
  assert.strictEqual(L.montant(1234567), '1 234 567 FCFA');
});

<?php

namespace Tests\Feature;

use App\Models\ConfigurationEcole;
use App\Models\EmploiTemps;
use App\Models\Evaluation;
use App\Models\FraisScolarite;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\NoteEvaluation;
use App\Models\Paiement;
use App\Models\Personnel;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModeHorsLigneTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected const LUNDI = '2026-10-05';

    private function jeton(User $user): string
    {
        $user->update(['password' => 'secret123']);

        return $this->postJson('/api/connexion', [
            'email' => $user->email, 'password' => 'secret123',
            'appareil_uuid' => (string) Str::uuid(), 'appareil_nom' => 'Téléphone test',
        ])->assertOk()->json('jeton');
    }

    private function envoyer(string $jeton, array $elements)
    {
        return $this->withToken($jeton)->postJson('/api/envoyer', ['elements' => $elements]);
    }

    private function element(string $type, array $donnees, ?string $uuid = null): array
    {
        return ['uuid' => $uuid ?? (string) Str::uuid(), 'type' => $type, 'donnees' => $donnees];
    }

    public function test_connexion_refusee_si_mot_de_passe_faux_ou_compte_sans_saisie(): void
    {
        $secretariat = $this->utilisateur('secretariat');
        $this->postJson('/api/connexion', [
            'email' => $secretariat->email, 'password' => 'mauvais',
            'appareil_uuid' => (string) Str::uuid(), 'appareil_nom' => 'PC',
        ])->assertStatus(422);

        $parent = $this->parentDe($this->eleve());
        $parent->update(['password' => 'secret123']);
        $this->postJson('/api/connexion', [
            'email' => $parent->email, 'password' => 'secret123',
            'appareil_uuid' => (string) Str::uuid(), 'appareil_nom' => 'PC',
        ])->assertStatus(422);

        $this->getJson('/api/donnees')->assertStatus(401);
        $this->withToken('faux-jeton')->getJson('/api/donnees')->assertStatus(401);
    }

    public function test_le_jeton_ne_reste_pas_en_clair_et_la_deconnexion_le_desactive(): void
    {
        $jeton = $this->jeton($this->utilisateur('direction'));

        $this->assertDatabaseMissing('appareils', ['jeton_hash' => $jeton]);
        $this->withToken($jeton)->getJson('/api/donnees')->assertOk();
        $this->withToken($jeton)->postJson('/api/deconnexion')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($jeton)->getJson('/api/donnees')->assertStatus(401);
    }

    public function test_appel_hors_ligne_enregistre_une_seule_fois_meme_si_renvoye(): void
    {
        $eleve = $this->eleve();
        $jeton = $this->jeton($this->utilisateur('direction'));

        $element = $this->element('appel', [
            'classe_id' => $eleve->classe_id, 'date' => self::LUNDI, 'statuts' => [$eleve->id => 'absent'],
        ]);
        $this->envoyer($jeton, [$element])->assertOk()->assertJsonPath('resultats.0.statut', 'accepte');
        $this->envoyer($jeton, [$element])->assertOk()->assertJsonPath('resultats.0.statut', 'accepte');

        $this->assertSame(1, Presence::count());
        $this->assertSame('absent', Presence::first()->statut);
    }

    public function test_un_enseignant_ne_fait_l_appel_que_pour_ses_cours(): void
    {
        $eleve = $this->eleve();
        $maths = Matiere::create(['cycle_id' => $eleve->classe->cycle_id, 'nom' => 'Mathématiques', 'coefficient' => 4]);
        $personnel = Personnel::create(['matricule' => 'PERS-1', 'nom' => 'ABAKAR', 'prenom' => 'Prof', 'type' => 'enseignant', 'salaire_base' => 0]);
        $prof = $this->utilisateur('enseignant');
        $prof->update(['personnel_id' => $personnel->id]);
        $autre = Personnel::create(['matricule' => 'PERS-2', 'nom' => 'DJIMET', 'prenom' => 'Prof', 'type' => 'enseignant', 'salaire_base' => 0]);
        $sonCours = EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'lundi', 'heure_debut' => '07:00', 'heure_fin' => '09:00', 'matiere_id' => $maths->id, 'enseignant_id' => $personnel->id]);
        $coursAutre = EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'lundi', 'heure_debut' => '09:00', 'heure_fin' => '10:00', 'matiere_id' => $maths->id, 'enseignant_id' => $autre->id]);
        $jeton = $this->jeton($prof->fresh());

        $donnees = $this->withToken($jeton)->getJson('/api/donnees')->assertOk();
        $this->assertSame([$sonCours->id], collect($donnees->json('appel.creneaux'))->pluck('id')->all());
        $this->assertFalse($donnees->json('appel.appel_journee'));
        $this->assertFalse($donnees->json('droits.paiement'));

        $resultats = $this->envoyer($jeton, [
            $this->element('appel', ['classe_id' => $eleve->classe_id, 'date' => self::LUNDI, 'cours' => $sonCours->id, 'statuts' => [$eleve->id => 'retard']]),
            $this->element('appel', ['classe_id' => $eleve->classe_id, 'date' => self::LUNDI, 'cours' => $coursAutre->id, 'statuts' => [$eleve->id => 'absent']]),
            $this->element('appel', ['classe_id' => $eleve->classe_id, 'date' => self::LUNDI, 'statuts' => [$eleve->id => 'absent']]),
            $this->element('paiement', ['eleve_id' => $eleve->id, 'types' => ['tranche1'], 'montant' => 5000, 'mode_paiement' => 'especes', 'date_paiement' => self::LUNDI]),
        ])->assertOk()->json('resultats');

        $this->assertSame(['accepte', 'refuse', 'refuse', 'refuse'], array_column($resultats, 'statut'));
        $this->assertSame(1, Presence::count());
        $this->assertSame(0, Paiement::count());
    }

    public function test_notes_hors_ligne_recalculent_la_moyenne_des_devoirs(): void
    {
        $eleve = $this->eleve();
        $maths = Matiere::create(['cycle_id' => $eleve->classe->cycle_id, 'nom' => 'Mathématiques', 'coefficient' => 4]);
        $devoir1 = Evaluation::create(['classe_id' => $eleve->classe_id, 'matiere_id' => $maths->id, 'trimestre' => 1, 'libelle' => 'Devoir 1', 'poids' => 50]);
        $devoir2 = Evaluation::create(['classe_id' => $eleve->classe_id, 'matiere_id' => $maths->id, 'trimestre' => 1, 'libelle' => 'Devoir 2', 'poids' => 50]);
        $jeton = $this->jeton($this->utilisateur('direction'));

        $donnees = $this->withToken($jeton)->getJson('/api/donnees')->assertOk();
        $this->assertCount(2, $donnees->json('notes.evaluations'));
        $this->assertContains(['classe_id' => $eleve->classe_id, 'matiere_id' => $maths->id], $donnees->json('notes.paires'));

        $this->envoyer($jeton, [$this->element('notes', [
            'classe_id' => $eleve->classe_id, 'matiere_id' => $maths->id, 'trimestre' => 1,
            'notes' => [$eleve->id => ['devoirs' => [$devoir1->id => 12, $devoir2->id => 16], 'moyenne_composition' => 11]],
        ])])->assertOk()->assertJsonPath('resultats.0.statut', 'accepte');

        $note = Note::first();
        $this->assertEquals(14, $note->moyenne_classe);
        $this->assertEquals(11, $note->moyenne_composition);
        $this->assertSame(2, NoteEvaluation::count());

        // Une note impossible est refusée avec la raison
        $refus = $this->envoyer($jeton, [$this->element('notes', [
            'classe_id' => $eleve->classe_id, 'matiere_id' => $maths->id, 'trimestre' => 1,
            'notes' => [$eleve->id => ['moyenne_composition' => 25]],
        ])])->json('resultats.0');
        $this->assertSame('refuse', $refus['statut']);
        $this->assertNotEmpty($refus['raison']);
    }

    public function test_encaissement_hors_ligne_recoit_le_vrai_numero_de_recu(): void
    {
        $eleve = $this->eleve();
        FraisScolarite::create(['cycle_id' => $eleve->classe->cycle_id, 'annee_scolaire' => ConfigurationEcole::anneeActive(), 'montant_annuel' => 100000]);
        $this->paiement($eleve);
        $comptable = $this->utilisateur('comptable', $eleve->classe->cycle);
        $jeton = $this->jeton($comptable);

        $donnees = $this->withToken($jeton)->getJson('/api/donnees')->assertOk();
        $this->assertEquals(100000, $donnees->json('encaissement.frais.' . $eleve->classe->cycle_id . '.montant_annuel'));
        $this->assertEquals(10000, $donnees->json('encaissement.verses.' . $eleve->id));

        $element = $this->element('paiement', [
            'eleve_id' => $eleve->id, 'types' => ['tranche1'], 'montant' => 25000,
            'mode_paiement' => 'mobile_money', 'date_paiement' => self::LUNDI, 'description' => 'Payé par le père',
        ]);
        $reference = $this->envoyer($jeton, [$element])->assertOk()->json('resultats.0.reference');
        $this->assertSame($reference, $this->envoyer($jeton, [$element])->json('resultats.0.reference'));

        $paiement = Paiement::where('numero_recu', $reference)->firstOrFail();
        $this->assertSame(2, Paiement::count());
        $this->assertEquals(25000, $paiement->montant);
        $this->assertSame($comptable->id, $paiement->saisi_par);
        $this->assertStringContainsString('hors ligne', $paiement->description);
    }

    public function test_un_comptable_n_encaisse_pas_hors_de_son_cycle(): void
    {
        $eleve = $this->eleve($this->cycle('Collège'));
        $jeton = $this->jeton($this->utilisateur('comptable', $this->cycle('Primaire')));

        $this->assertEmpty($this->withToken($jeton)->getJson('/api/donnees')->json('eleves'));
        $resultat = $this->envoyer($jeton, [$this->element('paiement', [
            'eleve_id' => $eleve->id, 'types' => ['tranche1'], 'montant' => 5000, 'mode_paiement' => 'especes', 'date_paiement' => self::LUNDI,
        ])])->json('resultats.0');

        $this->assertSame('refuse', $resultat['statut']);
        $this->assertSame(0, Paiement::count());
    }

    public function test_l_application_peut_appeler_l_api_depuis_un_autre_domaine(): void
    {
        $this->call('OPTIONS', '/api/connexion', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ])->assertHeader('Access-Control-Allow-Origin', '*');
    }
    public function test_la_direction_voit_et_deconnecte_les_appareils(): void
    {
        $direction = $this->utilisateur('direction');
        $jeton = $this->jeton($this->utilisateur('secretariat'));
        $appareil = \App\Models\Appareil::firstOrFail();

        $this->actingAs($this->utilisateur('secretariat'))->get('/appareils')->assertForbidden();
        $this->actingAs($direction)->get('/appareils')->assertOk()->assertSee('Téléphone test')->assertSee('Connecté');
        $this->actingAs($direction)->post('/appareils/' . $appareil->id . '/deconnecter')->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->withToken($jeton)->getJson('/api/donnees')->assertStatus(401);
    }
}

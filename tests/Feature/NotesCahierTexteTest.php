<?php

namespace Tests\Feature;

use App\Models\CahierTexte;
use App\Models\EmploiTemps;
use App\Models\Enseignant;
use App\Models\Evaluation;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesCahierTexteTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    // Classe de primaire, deux élèves, un enseignant de calcul (fiche matière + emploi du temps)
    protected function contexte(): array
    {
        $cycle = $this->cycle('Primaire');
        $premier = $this->eleve($cycle, 'Premier');
        $second = $this->eleve($cycle, 'Second');
        $enseignant = Enseignant::create(['nom' => 'DJIMET', 'prenom' => 'Paul']);
        $personnel = Personnel::create(['matricule' => 'P-1', 'nom' => 'DJIMET', 'prenom' => 'Paul', 'type' => 'enseignant', 'salaire_base' => 0]);
        $calcul = Matiere::create(['cycle_id' => $cycle->id, 'enseignant_id' => $enseignant->id, 'nom' => 'Calcul', 'coefficient' => 3, 'categorie' => 'Scientifique']);
        $lecture = Matiere::create(['cycle_id' => $cycle->id, 'nom' => 'Lecture', 'coefficient' => 2, 'categorie' => 'Littéraire']);
        $prof = $this->utilisateur('enseignant', $cycle);
        $prof->update(['enseignant_id' => $enseignant->id, 'personnel_id' => $personnel->id]);
        EmploiTemps::create(['classe_id' => $premier->classe_id, 'jour' => 'lundi', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'matiere_id' => $calcul->id, 'enseignant_id' => $personnel->id]);

        return compact('cycle', 'premier', 'second', 'calcul', 'lecture', 'prof');
    }

    public function test_plusieurs_devoirs_avec_pourcentages_et_composition(): void
    {
        ['premier' => $premier, 'second' => $second, 'calcul' => $calcul, 'prof' => $prof] = $this->contexte();
        $ctx = ['classe_id' => $premier->classe_id, 'matiere_id' => $calcul->id, 'trimestre' => 1];

        $this->actingAs($prof)->post(route('notes.devoirs.store'), $ctx + ['libelle' => 'Interrogation', 'poids' => 40])->assertRedirect();
        $this->actingAs($prof)->get(route('notes.saisie', $ctx))->assertOk()->assertSee('40 %')->assertSee('il faut arriver à 100 %');
        $this->actingAs($prof)->post(route('notes.devoirs.store'), $ctx + ['libelle' => 'Devoir surveillé', 'poids' => 60])->assertRedirect();
        [$interro, $ds] = Evaluation::orderBy('id')->get()->all();
        $this->actingAs($prof)->get(route('notes.saisie', $ctx))->assertOk()->assertDontSee('il faut arriver à 100 %');

        $this->actingAs($prof)->post(route('notes.enregistrer'), $ctx + ['notes' => [
            $premier->id => ['devoirs' => [$interro->id => 12, $ds->id => 15], 'moyenne_composition' => 14],
            $second->id => ['devoirs' => [$interro->id => '', $ds->id => 10], 'moyenne_composition' => 8],   // absent à l'interrogation
        ]])->assertRedirect();

        $notePremier = Note::where('eleve_id', $premier->id)->first();
        $this->assertSame(13.8, (float) $notePremier->moyenne_classe);          // 12×40 % + 15×60 %
        $this->assertSame(13.9, $notePremier->moyenne_matiere);                  // (13,8 + 14) / 2
        $this->assertSame(10.0, (float) Note::where('eleve_id', $second->id)->value('moyenne_classe'));   // seul le DS compte

        // Changer un pourcentage recalcule les moyennes
        $this->actingAs($prof)->put(route('notes.devoirs.update', $interro), ['libelle' => 'Interrogation', 'poids' => 50])->assertRedirect();
        $this->actingAs($prof)->put(route('notes.devoirs.update', $ds), ['libelle' => 'Devoir surveillé', 'poids' => 50])->assertRedirect();
        $this->assertSame(13.5, (float) $notePremier->fresh()->moyenne_classe);

        // Supprimer un devoir (droit « Modifier » suffit)
        $this->actingAs($prof)->post(route('notes.devoirs.destroy', $interro))->assertRedirect();
        $this->assertSame(15.0, (float) $notePremier->fresh()->moyenne_classe);
    }

    public function test_l_enseignant_ne_note_que_sa_matiere(): void
    {
        ['premier' => $premier, 'lecture' => $lecture, 'prof' => $prof] = $this->contexte();

        $this->actingAs($prof)->get(route('notes.saisie', ['classe_id' => $premier->classe_id, 'matiere_id' => $lecture->id, 'trimestre' => 1]))->assertForbidden();
        $this->actingAs($prof)->post(route('notes.devoirs.store'), ['classe_id' => $premier->classe_id, 'matiere_id' => $lecture->id, 'trimestre' => 1, 'libelle' => 'X', 'poids' => 50])->assertForbidden();
    }

    public function test_le_coefficient_est_fixe_par_la_direction_ou_le_principal_du_cycle(): void
    {
        ['cycle' => $primaire, 'calcul' => $calcul] = $this->contexte();
        $donnees = fn ($coef) => ['cycle_id' => $primaire->id, 'nom' => 'Calcul', 'coefficient' => $coef, 'categorie' => 'Scientifique'];

        $this->actingAs($this->utilisateur('secretariat'))->put(route('matieres-gestion.update', $calcul), $donnees(8))->assertRedirect();
        $this->assertSame(3, (int) $calcul->fresh()->coefficient);                // inchangé

        $this->actingAs($this->utilisateur('principal', $this->cycle('Collège')))->put(route('matieres-gestion.update', $calcul), $donnees(8));
        $this->assertSame(3, (int) $calcul->fresh()->coefficient);                // principal d'un autre cycle

        $this->actingAs($this->utilisateur('principal', $primaire))->put(route('matieres-gestion.update', $calcul), $donnees(4))->assertRedirect();
        $this->assertSame(4, (int) $calcul->fresh()->coefficient);                // directeur du primaire

        $this->actingAs($this->utilisateur('direction'))->put(route('matieres-gestion.update', $calcul), $donnees(5));
        $this->assertSame(5, (int) $calcul->fresh()->coefficient);

        // Matière créée par le secrétariat : coefficient 1, à ajuster par le Principal
        $this->actingAs($this->utilisateur('secretariat'))->post(route('matieres-gestion.store'), ['cycle_id' => $primaire->id, 'nom' => 'Dessin', 'coefficient' => 6]);
        $this->assertSame(1, (int) Matiere::where('nom', 'Dessin')->value('coefficient'));
    }

    public function test_cahier_de_texte_rempli_par_l_enseignant_suivi_et_vise_par_le_principal(): void
    {
        ['cycle' => $cycle, 'premier' => $premier, 'calcul' => $calcul, 'lecture' => $lecture, 'prof' => $prof] = $this->contexte();
        $date = now()->startOfWeek()->subWeek()->toDateString();   // un lundi passé

        $this->actingAs($prof)->post(route('cahier-texte.store'), [
            'classe_id' => $premier->classe_id, 'matiere_id' => $calcul->id, 'date' => $date,
            'titre' => 'Les fractions', 'contenu' => 'Définition et exemples', 'travail_a_faire' => 'Exercices 4 et 5 page 32',
        ])->assertRedirect();
        $seance = CahierTexte::firstOrFail();

        // Autre matière, séance future : refusé
        $this->actingAs($prof)->post(route('cahier-texte.store'), ['classe_id' => $premier->classe_id, 'matiere_id' => $lecture->id, 'date' => $date, 'titre' => 'X'])->assertForbidden();
        $this->actingAs($prof)->post(route('cahier-texte.store'), ['classe_id' => $premier->classe_id, 'matiere_id' => $calcul->id, 'date' => now()->addDay()->toDateString(), 'titre' => 'X'])->assertSessionHasErrors('date');

        // L'enseignant ne vise pas
        $this->actingAs($prof)->post(route('cahier-texte.viser', $seance), ['visa' => 'Bien'])->assertForbidden();

        $principal = $this->utilisateur('principal', $cycle);
        $this->actingAs($principal)->get(route('cahier-texte.index', ['du' => now()->subWeeks(3)->toDateString(), 'au' => now()->toDateString()]))
            ->assertOk()->assertSee('Les fractions')->assertSee('Suivi des séances')->assertSee('DJIMET');
        $this->actingAs($principal)->post(route('cahier-texte.viser', $seance), ['visa' => 'Bonne progression'])->assertRedirect();
        $this->assertNotNull($seance->fresh()->vise_le);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ClasseScolaire;
use App\Models\EmploiTemps;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfesseurPrincipalTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    private function enseignant(string $nom, string $type = 'enseignant'): Personnel
    {
        return Personnel::create(['matricule' => 'P-' . uniqid(), 'nom' => $nom, 'prenom' => 'Prof', 'type' => $type, 'salaire_base' => 0]);
    }

    public function test_le_professeur_principal_se_choisit_dans_le_personnel_enseignant(): void
    {
        $eleve = $this->eleve();
        $classe = $eleve->classe;
        $mahamat = $this->enseignant('MAHAMAT');
        $abakar = $this->enseignant('ABAKAR');
        $gardien = $this->enseignant('GARDIEN', 'securite');
        $maths = Matiere::create(['cycle_id' => $classe->cycle_id, 'nom' => 'Mathématiques', 'coefficient' => 4]);
        EmploiTemps::create(['classe_id' => $classe->id, 'jour' => 'lundi', 'heure_debut' => '07:00', 'heure_fin' => '08:00', 'matiere_id' => $maths->id, 'enseignant_id' => $abakar->id]);
        $direction = $this->utilisateur('direction');

        $this->actingAs($direction)->get(route('classes.edit', $classe->id))->assertOk()
            ->assertSeeInOrder(['Ont cours dans cette classe', 'ABAKAR', 'Autres enseignants', 'MAHAMAT'])
            ->assertDontSee('GARDIEN');

        $this->actingAs($direction)->put(route('classes.update', $classe->id), [
            'cycle_id' => $classe->cycle_id, 'nom' => $classe->nom, 'prof_principal_personnel_id' => $gardien->id,
        ])->assertSessionHasErrors('prof_principal_personnel_id');

        $this->actingAs($direction)->put(route('classes.update', $classe->id), [
            'cycle_id' => $classe->cycle_id, 'nom' => $classe->nom, 'prof_principal_personnel_id' => $mahamat->id,
        ])->assertRedirect(route('classes.index'));

        $this->assertSame($mahamat->id, $classe->fresh()->prof_principal_personnel_id);
        $this->actingAs($direction)->get(route('classes.index'))->assertSee('Prof MAHAMAT');
    }

    public function test_le_compte_lie_a_la_fiche_personnel_accede_aux_bulletins_de_sa_classe(): void
    {
        $eleve = $this->eleve();
        $mahamat = $this->enseignant('MAHAMAT');
        ClasseScolaire::whereKey($eleve->classe_id)->update(['prof_principal_personnel_id' => $mahamat->id]);

        $prof = $this->utilisateur('enseignant');
        $prof->update(['personnel_id' => $mahamat->id]);
        $autre = $this->utilisateur('enseignant');
        $autre->update(['personnel_id' => $this->enseignant('DJIMET')->id]);

        $this->actingAs($prof->fresh())->get(route('bulletins.choix'))->assertOk()->assertSee($eleve->classe->nom);
        $this->actingAs($prof->fresh())->get(route('bulletins.show', [$eleve, 1]))->assertOk()->assertSee('Prof MAHAMAT');
        $this->actingAs($prof->fresh())->get('/conseil-classe/saisie?classe_id=' . $eleve->classe_id . '&trimestre=1')->assertOk();

        $this->actingAs($autre->fresh())->get(route('bulletins.show', [$eleve, 1]))->assertForbidden();
        $this->actingAs($autre->fresh())->get('/conseil-classe/saisie?classe_id=' . $eleve->classe_id . '&trimestre=1')->assertNotFound();
    }

    public function test_le_nouveau_choix_remplace_l_ancienne_fiche_enseignant(): void
    {
        $eleve = $this->eleve();
        $ancien = Enseignant::create(['nom' => 'ANCIEN', 'prenom' => 'Prof']);
        ClasseScolaire::whereKey($eleve->classe_id)->update(['prof_principal_id' => $ancien->id]);
        $compteAncien = $this->utilisateur('enseignant');
        $compteAncien->update(['enseignant_id' => $ancien->id]);

        // Tant que la classe n'est pas mise à jour, l'ancien choix fonctionne toujours
        $this->actingAs($compteAncien->fresh())->get(route('bulletins.show', [$eleve, 1]))->assertOk();

        $nouveau = $this->enseignant('NOUVEAU');
        $this->actingAs($this->utilisateur('direction'))->put(route('classes.update', $eleve->classe_id), [
            'cycle_id' => $eleve->classe->cycle_id, 'nom' => $eleve->classe->nom, 'prof_principal_personnel_id' => $nouveau->id,
        ]);

        $this->assertNull($eleve->classe->fresh()->prof_principal_id);
        $this->actingAs($compteAncien->fresh())->get(route('bulletins.show', [$eleve, 1]))->assertForbidden();
    }
}

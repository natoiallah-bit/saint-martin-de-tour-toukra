<?php

namespace Tests\Feature;

use App\Models\DossierDiscipline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisciplineTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    public function test_convocation_alerte_le_parent_puis_decision(): void
    {
        $cycle = $this->cycle('Collège');
        $eleve = $this->eleve($cycle, 'MOUSSA');
        $parent = $this->parentDe($eleve);
        $surveillant = $this->utilisateur('surveillant', $cycle);

        $this->actingAs($surveillant)->post(route('discipline.store'), [
            'eleve_id' => $eleve->id, 'motif' => 'Bagarre dans la cour', 'faits' => 'Coups échangés à la récréation',
            'date_faits' => now()->subDay()->toDateString(), 'date_conseil' => now()->addDays(3)->format('Y-m-d 10:00'), 'lieu' => 'Bureau du Directeur',
        ])->assertRedirect();
        $dossier = DossierDiscipline::firstOrFail();

        // Le parent voit l'alerte (bandeau + cloche) avec le motif
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()
            ->assertSee('Convocation au conseil de discipline')->assertSee('Bagarre dans la cour')->assertSee('Bureau du Directeur')
            ->assertSee('🔔 1 alerte');
        $this->actingAs($parent)->post(route('discipline.lu', $dossier))->assertRedirect();
        $this->assertNotNull($dossier->fresh()->convocation_lue_le);
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertDontSee('J&#039;ai pris connaissance', false)->assertDontSee('🔔');

        // Décision : nouvelle alerte au parent
        $this->actingAs($surveillant)->post(route('discipline.decider', $dossier), [
            'decision' => 'exclusion_temporaire', 'decision_details' => '3 jours, du 20 au 22', 'date_decision' => now()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()
            ->assertSee('Décision du conseil de discipline')->assertSee('Exclusion temporaire')->assertSee('3 jours, du 20 au 22');
        $this->actingAs($parent)->post(route('discipline.lu', $dossier));
        $this->assertNotNull($dossier->fresh()->decision_lue_le);

        // La direction voit que le parent a lu ; convocation imprimable
        $direction = $this->utilisateur('direction');
        $this->actingAs($direction)->get(route('discipline.index'))->assertOk()->assertSee('MOUSSA')->assertSee('✔ Lu');
        $this->actingAs($direction)->get(route('discipline.convocation', $dossier))->assertOk()->assertSee('Bagarre dans la cour');
        $this->actingAs($direction)->get(route('recherche.eleve', $eleve))->assertOk()->assertSee('Conseil de discipline')->assertSee('Exclusion temporaire');
    }

    public function test_acces_limite(): void
    {
        $eleve = $this->eleve($this->cycle('Collège'), 'MOUSSA');
        $dossier = DossierDiscipline::create(['eleve_id' => $eleve->id, 'motif' => 'Retards']);

        // Un autre parent ne peut pas confirmer ; un enseignant n'a pas accès
        $this->actingAs($this->parentDe($this->eleve($this->cycle('Primaire'), 'Autre')))->post(route('discipline.lu', $dossier))->assertForbidden();
        $this->actingAs($this->utilisateur('enseignant'))->get(route('discipline.index'))->assertForbidden();
        // Surveillant d'un autre cycle
        $this->actingAs($this->utilisateur('surveillant', $this->cycle('Lycée')))->get(route('discipline.show', $dossier))->assertForbidden();
        // Le surveillant enregistre, mais seule la Direction ou le Principal supprime
        $this->actingAs($this->utilisateur('surveillant'))->delete(route('discipline.destroy', $dossier))->assertForbidden();
        $this->actingAs($this->utilisateur('principal'))->delete(route('discipline.destroy', $dossier))->assertRedirect();
    }
}

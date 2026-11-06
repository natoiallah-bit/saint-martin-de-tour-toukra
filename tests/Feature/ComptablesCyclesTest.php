<?php

namespace Tests\Feature;

use App\Models\OperationFinanciere;
use App\Models\Paie;
use App\Models\Paiement;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComptablesCyclesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function contexte(): array
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');

        return [
            'primaire' => $primaire,
            'college' => $college,
            'elevePrimaire' => $this->eleve($primaire, 'Petit'),
            'eleveCollege' => $this->eleve($college, 'Grand'),
            'comptablePrimaire' => $this->utilisateur('comptable', $primaire),
            'comptableCollege' => $this->utilisateur('comptable', $college),
        ];
    }

    public function test_chaque_comptable_ne_voit_que_les_paiements_de_son_cycle(): void
    {
        extract($this->contexte());
        $recuCollege = $this->paiement($eleveCollege);

        $this->actingAs($comptablePrimaire)->post('/paiements', [
            'eleve_id' => $elevePrimaire->id, 'types' => ['inscription'], 'montant' => 25000,
            'mode_paiement' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertRedirect();
        $recuPrimaire = Paiement::where('eleve_id', $elevePrimaire->id)->firstOrFail();

        $this->actingAs($comptablePrimaire)->get('/paiements')->assertOk()->assertSee($recuPrimaire->numero_recu)->assertDontSee($recuCollege->numero_recu);
        $this->actingAs($comptablePrimaire)->get(route('paiements.recu', $recuCollege))->assertForbidden();
        $this->actingAs($comptablePrimaire)->delete(route('paiements.destroy', $recuCollege))->assertForbidden();
        $this->actingAs($comptablePrimaire)->post('/paiements', [
            'eleve_id' => $eleveCollege->id, 'types' => ['inscription'], 'montant' => 1000,
            'mode_paiement' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($comptableCollege)->get('/paiements')->assertOk()->assertSee($recuCollege->numero_recu)->assertDontSee($recuPrimaire->numero_recu);
        $this->actingAs($this->utilisateur('direction'))->get('/paiements')->assertOk()->assertSee($recuCollege->numero_recu)->assertSee($recuPrimaire->numero_recu);
    }

    public function test_recettes_depenses_par_cycle_et_commun_pour_la_direction(): void
    {
        extract($this->contexte());
        $operation = fn (array $donnees) => ['type' => 'depense', 'categorie' => 'Fournitures scolaires', 'montant' => 5000, 'date_operation' => now()->toDateString()] + $donnees;

        $this->actingAs($comptablePrimaire)->post(route('operations.store'), $operation(['description' => 'Craies primaire', 'cycle_id' => $college->id]))->assertRedirect();
        $this->assertSame($primaire->id, (int) OperationFinanciere::where('description', 'Craies primaire')->value('cycle_id'));   // cycle imposé

        $this->actingAs($this->utilisateur('direction'))->post(route('operations.store'), $operation(['description' => 'Gardiennage']))->assertRedirect();
        $commune = OperationFinanciere::where('description', 'Gardiennage')->firstOrFail();
        $this->assertNull($commune->cycle_id);

        $this->actingAs($comptableCollege)->get(route('operations.index'))->assertOk()->assertDontSee('Craies primaire')->assertDontSee('Gardiennage');
        $this->actingAs($comptablePrimaire)->get(route('operations.index'))->assertOk()->assertSee('Craies primaire')->assertDontSee('Gardiennage');
        $this->actingAs($comptablePrimaire)->delete(route('operations.destroy', $commune))->assertForbidden();

        $direction = $this->utilisateur('direction');
        $this->actingAs($direction)->get(route('operations.index'))->assertOk()->assertSee('Craies primaire')->assertSee('Gardiennage');
        $this->actingAs($direction)->get(route('operations.index', ['cycle' => 'commun']))->assertOk()->assertSee('Gardiennage')->assertDontSee('Craies primaire');
    }

    public function test_paie_et_frais_limites_au_cycle(): void
    {
        extract($this->contexte());
        $instit = Personnel::create(['matricule' => 'P-1', 'nom' => 'INSTIT', 'prenom' => 'A', 'type' => 'enseignant', 'salaire_base' => 100000, 'actif' => true, 'cycle_id' => $primaire->id]);
        $prof = Personnel::create(['matricule' => 'P-2', 'nom' => 'PROFCOLLEGE', 'prenom' => 'B', 'type' => 'enseignant', 'salaire_base' => 150000, 'actif' => true, 'cycle_id' => $college->id]);
        $gardien = Personnel::create(['matricule' => 'P-3', 'nom' => 'GARDIEN', 'prenom' => 'C', 'type' => 'securite', 'salaire_base' => 60000, 'actif' => true]);
        $paieProf = Paie::create(['personnel_id' => $prof->id, 'mois' => now()->format('Y-m'), 'salaire_base' => 150000, 'primes' => 0, 'acompte' => 0, 'retenues' => 0]);

        $this->actingAs($comptablePrimaire)->get(route('paies.index'))->assertOk()->assertSee('INSTIT')->assertDontSee('PROFCOLLEGE')->assertDontSee('GARDIEN');
        $this->actingAs($comptablePrimaire)->get(route('paies.bulletin', $paieProf))->assertForbidden();
        $this->actingAs($comptablePrimaire)->post(route('paies.store'), ['personnel_id' => $gardien->id, 'mois' => now()->format('Y-m'), 'salaire_base' => 60000, 'date_paiement' => now()->toDateString(), 'mode_paiement' => 'Espèces'])->assertForbidden();
        $this->actingAs($this->utilisateur('direction'))->get(route('paies.index'))->assertOk()->assertSee('GARDIEN');

        $this->actingAs($comptablePrimaire)->get(route('frais.index'))->assertOk()->assertSee('Primaire')->assertDontSee('Collège');
    }

    public function test_fiche_et_recherche_du_personnel(): void
    {
        $primaire = $this->cycle('Primaire');
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->post(route('personnels.store'), [
            'nom' => 'MAHAMAT', 'prenom' => 'Ali', 'type' => 'enseignant', 'fonction' => 'Instituteur', 'salaire_base' => 120000,
            'telephone' => '+235 66 00 00 01', 'statut' => 'contractuel', 'date_embauche' => '2023-09-01', 'cycle_id' => $primaire->id, 'actif' => 1,
        ])->assertRedirect();
        $ali = Personnel::where('nom', 'MAHAMAT')->firstOrFail();
        Personnel::create(['matricule' => 'P-9', 'nom' => 'ZAKARIA', 'prenom' => 'Moussa', 'type' => 'entretien', 'salaire_base' => 0, 'actif' => true]);

        $this->actingAs($secretariat)->get(route('personnels.index', ['q' => 'mahamat']))->assertOk()->assertSee('MAHAMAT')->assertDontSee('ZAKARIA');
        $this->actingAs($secretariat)->get(route('personnels.index', ['statut' => 'contractuel']))->assertOk()->assertSee('MAHAMAT')->assertDontSee('ZAKARIA');

        // Fiche complète : contrat, ancienneté, cycle ; le salaire seulement avec le droit Paie
        $this->actingAs($secretariat)->get(route('personnels.show', $ali))->assertOk()
            ->assertSee('Contractuel')->assertSee('01/09/2023')->assertSee('Primaire')->assertSee('Instituteur')
            ->assertSee('120 000 FCFA');   // le secrétariat a la paie par défaut
        \App\Models\DroitRole::where(['role' => 'secretariat', 'module' => 'paie'])->update(['niveau' => 0]);
        $this->actingAs($secretariat->fresh())->get(route('personnels.show', $ali))->assertOk()->assertDontSee('120 000 FCFA');

        $this->actingAs($secretariat)->get(route('personnels.badges'))->assertOk();   // la route des badges marche toujours
    }
}

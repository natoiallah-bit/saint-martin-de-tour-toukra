<?php

namespace Tests\Feature;

use App\Models\ConfigurationEcole;
use App\Models\FraisScolarite;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RapportsTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function setUp(): void
    {
        parent::setUp();
        ConfigurationEcole::actuelle()->update([
            'annee_scolaire_active' => '2026-2027', 'coefficient_conduite' => 0,
            'trimestre1_debut' => '2026-10-01', 'trimestre1_fin' => '2026-12-20',
            'trimestre2_debut' => '2027-01-05', 'trimestre2_fin' => '2027-03-31',
            'trimestre3_debut' => '2027-04-10', 'trimestre3_fin' => '2027-06-30',
        ]);
    }

    // Primaire : 2 élèves notés (14 et 8), frais avec échéancier ; Collège : 1 élève
    protected function donnees(): array
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $bon = $this->eleve($primaire, 'Premier');
        $bon->update(['sexe' => 'F']);
        $faible = $this->eleve($primaire, 'Second');
        $faible->update(['sexe' => 'M']);
        $collegien = $this->eleve($college, 'Collegien');

        $maths = Matiere::create(['cycle_id' => $primaire->id, 'nom' => 'Calcul', 'coefficient' => 2, 'categorie' => 'Scientifique']);
        Note::create(['eleve_id' => $bon->id, 'matiere_id' => $maths->id, 'trimestre' => 1, 'moyenne_classe' => 14, 'moyenne_composition' => 14]);
        Note::create(['eleve_id' => $faible->id, 'matiere_id' => $maths->id, 'trimestre' => 1, 'moyenne_classe' => 8, 'moyenne_composition' => 8]);

        FraisScolarite::create([
            'cycle_id' => $primaire->id, 'annee_scolaire' => '2026-2027', 'montant_annuel' => 100000,
            'montant_inscription' => 10000, 'montant_tranche1' => 30000, 'date_tranche1' => '2026-11-15',
            'montant_tranche2' => 30000, 'date_tranche2' => '2027-02-15', 'montant_tranche3' => 30000, 'date_tranche3' => '2027-05-15',
        ]);
        foreach ([[$bon, 40000, '2026-10-10'], [$faible, 15000, '2026-11-02'], [$bon, 30000, '2027-01-20']] as [$eleve, $montant, $date]) {
            Paiement::create(['eleve_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'types' => ['tranche1'], 'montant' => $montant,
                'mode_paiement' => 'especes', 'date_paiement' => $date, 'numero_recu' => Paiement::genererNumeroRecu()]);
        }
        OperationFinanciere::create(['type' => 'depense', 'categorie' => 'Électricité / Eau', 'montant' => 12000, 'mode_paiement' => 'especes', 'date_operation' => '2026-11-05']);

        return compact('primaire', 'college', 'bon', 'faible', 'collegien');
    }

    public function test_rapport_general_de_la_direction(): void
    {
        $this->donnees();

        $this->actingAs($this->utilisateur('direction'))
            ->get(route('rapports.index', ['periode' => 't1']))
            ->assertOk()
            ->assertSee('Rapport général — 1er trimestre 2026-2027')
            ->assertSee('Volet pédagogique')->assertSee('Volet financier')
            ->assertSee('11,00')                       // moyenne de la classe : (14 + 8) / 2
            ->assertSee('50 %')                        // 1 élève sur 2 a la moyenne
            ->assertSee('Premier')                     // tableau d'honneur (14 ≥ 12)
            ->assertSee('80 000 FCFA')                 // dû fin T1 : 2 × 40 000
            ->assertSee('55 000 FCFA')                 // encaissé au 1er trimestre
            ->assertSee('25 000 FCFA')                 // reste dû (le second doit 25 000)
            ->assertSee('Électricité / Eau');          // dépenses de l'établissement
    }

    public function test_rapport_annuel(): void
    {
        $this->donnees();

        $this->actingAs($this->utilisateur('direction'))
            ->get(route('rapports.index', ['periode' => 'annee']))
            ->assertOk()
            ->assertSee('Année scolaire 2026-2027')
            ->assertSee('Attendu (année)')
            ->assertSee('200 000 FCFA')                // 2 × 100 000
            ->assertSee('85 000 FCFA');                // tout ce qui a été encaissé dans l'année
    }

    public function test_le_principal_du_primaire_ne_voit_que_la_pedagogie_de_son_cycle(): void
    {
        ['primaire' => $primaire] = $this->donnees();

        $this->actingAs($this->utilisateur('principal', $primaire))
            ->get(route('rapports.index', ['periode' => 't1']))
            ->assertOk()
            ->assertSee('Rapport pédagogique')
            ->assertSee('Cycle : Primaire')
            ->assertSee('Volet pédagogique')
            ->assertDontSee('Volet financier')
            ->assertDontSee('Classe Collège')
            ->assertDontSee('name="cycle_id"', false);      // pas de choix de cycle
    }

    public function test_le_comptable_ne_voit_que_les_finances_de_son_cycle(): void
    {
        ['college' => $college] = $this->donnees();

        $this->actingAs($this->utilisateur('comptable', $college))
            ->get(route('rapports.index', ['periode' => 't1']))
            ->assertOk()
            ->assertSee('Rapport financier')
            ->assertSee('Volet financier')
            ->assertDontSee('Volet pédagogique')
            ->assertDontSee('Classe Primaire')
            ->assertDontSee('Recettes et dépenses de l&#039;établissement', false);   // seulement pour toute l'école
    }

    public function test_les_autres_roles_n_ont_pas_acces_et_le_bouton_est_sur_l_accueil(): void
    {
        $this->actingAs($this->utilisateur('enseignant'))->get(route('rapports.index'))->assertForbidden();
        $this->actingAs($this->utilisateur('surveillant'))->get(route('rapports.index'))->assertForbidden();

        $this->actingAs($this->utilisateur('direction'))->get(route('dashboard'))
            ->assertOk()->assertSee('Rapport trimestriel / annuel');
        $this->actingAs($this->utilisateur('principal', $this->cycle()))->get(route('dashboard'))
            ->assertOk()->assertSee('Rapport trimestriel / annuel');
    }
}

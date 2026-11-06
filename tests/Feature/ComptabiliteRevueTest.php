<?php

namespace Tests\Feature;

use App\Models\ConfigurationEcole;
use App\Models\FraisScolarite;
use App\Models\JournalFinancier;
use App\Models\Materiel;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use App\Models\Paie;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComptabiliteRevueTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function setUp(): void
    {
        parent::setUp();
        ConfigurationEcole::actuelle()->update(['annee_scolaire_active' => '2026-2027']);
    }

    public function test_la_paie_cree_et_met_a_jour_la_depense_salaires(): void
    {
        $primaire = $this->cycle('Primaire');
        $instit = Personnel::create(['matricule' => 'P-1', 'nom' => 'INSTIT', 'prenom' => 'Awa', 'type' => 'enseignant', 'salaire_base' => 100000, 'actif' => true, 'cycle_id' => $primaire->id]);
        $comptable = $this->utilisateur('comptable', $primaire);
        $paie = ['personnel_id' => $instit->id, 'mois' => '2026-10', 'salaire_base' => 100000, 'primes' => 10000, 'acompte' => 20000, 'retenues' => 0, 'date_paiement' => '2026-10-30', 'mode_paiement' => 'Virement bancaire'];

        $this->actingAs($comptable)->post(route('paies.store'), $paie)->assertRedirect();
        $depense = OperationFinanciere::whereNotNull('paie_id')->firstOrFail();
        $this->assertSame('Salaires', $depense->categorie);
        $this->assertEquals(90000, $depense->montant);   // net : 100 000 + 10 000 − 20 000
        $this->assertSame($primaire->id, $depense->cycle_id);
        $this->assertSame('Virement bancaire', $depense->mode_paiement);
        $this->assertSame('2026-2027', $depense->annee_scolaire);

        // Bulletin refait : la dépense suit, l'ancien montant est au journal
        $this->actingAs($comptable)->post(route('paies.store'), ['primes' => 30000] + $paie)->assertRedirect();
        $this->assertSame(1, OperationFinanciere::whereNotNull('paie_id')->count());
        $this->assertEquals(110000, $depense->fresh()->montant);
        $this->assertTrue(JournalFinancier::where('objet', 'paie')->where('action', 'modification')->exists());

        // Le salaire n'est ni corrigeable ni supprimable depuis la Comptabilité
        $this->actingAs($comptable)->get(route('operations.edit', $depense))->assertForbidden();

        // Suppression du bulletin : motif obligatoire, la dépense disparaît
        $bulletin = Paie::firstOrFail();
        $this->actingAs($comptable)->delete(route('paies.destroy', $bulletin))->assertSessionHasErrors('motif');
        $this->actingAs($comptable)->delete(route('paies.destroy', $bulletin), ['motif' => 'Doublon'])->assertRedirect();
        $this->assertSame(0, OperationFinanciere::count());
        $this->assertTrue(JournalFinancier::where('objet', 'paie')->where('action', 'suppression')->where('motif', 'Doublon')->exists());
    }

    public function test_les_totaux_portent_sur_l_annee_active_et_le_mois_choisi(): void
    {
        $direction = $this->utilisateur('direction');
        OperationFinanciere::create(['type' => 'depense', 'categorie' => 'Carburant', 'montant' => 7000, 'mode_paiement' => 'Espèces', 'date_operation' => '2026-10-05']);
        OperationFinanciere::create(['type' => 'depense', 'categorie' => 'Carburant', 'montant' => 3000, 'mode_paiement' => 'Espèces', 'date_operation' => '2026-11-05']);
        OperationFinanciere::create(['type' => 'depense', 'categorie' => 'Ancienne', 'montant' => 500000, 'mode_paiement' => 'Espèces', 'date_operation' => '2025-10-05', 'annee_scolaire' => '2025-2026']);

        $this->actingAs($direction)->get(route('operations.index'))->assertOk()
            ->assertViewHas('totalDepenses', 10000.0)
            ->assertDontSee('Ancienne');
        $this->actingAs($direction)->get(route('operations.index', ['mois' => '2026-11']))->assertOk()
            ->assertViewHas('totalDepenses', 3000.0);
        $this->actingAs($direction)->get(route('dashboard'))->assertOk()->assertViewHas('totalDepenses', 10000);
    }

    public function test_formulaire_de_recette_garde_la_categorie_choisie(): void
    {
        $this->actingAs($this->utilisateur('direction'))->post(route('operations.store'), [
            'type' => 'recette', 'categorie' => 'Don', 'montant' => 50000, 'mode_paiement' => 'Espèces', 'date_operation' => '2026-10-10',
        ])->assertRedirect();
        $this->assertSame('Don', OperationFinanciere::firstOrFail()->categorie);
    }

    public function test_eleve_exonere_n_a_rien_a_payer_partout(): void
    {
        $cycle = $this->cycle('Primaire');
        FraisScolarite::create(['cycle_id' => $cycle->id, 'annee_scolaire' => '2026-2027', 'montant_annuel' => 100000]);
        $exonere = $this->eleve($cycle, 'EXO');
        $exonere->update(['exonere' => true]);
        $payant = $this->eleve($cycle, 'PAYANT');
        $paiement = $this->paiement($exonere);
        $this->paiement($payant);

        $this->actingAs($this->utilisateur('secretariat'))->get(route('paiements.recu', $paiement))->assertOk()->assertSee('Exonéré(e)');
        $this->actingAs($this->parentDe($exonere))->get(route('parent.dashboard'))->assertOk()->assertSee('Exonéré(e)');
        // Taux de recouvrement : seul l'élève payant compte (20 000 / 100 000)
        $this->actingAs($this->utilisateur('direction'))->get(route('dashboard'))->assertOk()->assertViewHas('tauxRecouvrement', 20.0);
    }

    public function test_correction_et_suppression_d_un_paiement_gardees_au_journal(): void
    {
        $eleve = $this->eleve();
        $paiement = $this->paiement($eleve);
        $secretariat = $this->utilisateur('secretariat');
        $donnees = ['types' => ['tranche1'], 'montant' => 15000, 'mode_paiement' => 'especes', 'date_paiement' => now()->toDateString()];

        $this->actingAs($secretariat)->get(route('paiements.edit', $paiement))->assertOk()->assertSee($paiement->numero_recu);
        $this->actingAs($secretariat)->put(route('paiements.update', $paiement), $donnees)->assertSessionHasErrors('motif');
        $this->actingAs($secretariat)->put(route('paiements.update', $paiement), $donnees + ['motif' => 'Montant mal saisi'])->assertRedirect();

        $this->assertEquals(15000, $paiement->fresh()->montant);
        $this->assertSame($paiement->numero_recu, $paiement->fresh()->numero_recu);
        $ligne = JournalFinancier::where('objet', 'paiement')->firstOrFail();
        $this->assertEquals(10000, $ligne->avant['montant']);
        $this->assertEquals(15000, $ligne->apres['montant']);
        $this->actingAs($secretariat)->get(route('paiements.recu', $paiement))->assertSee('Reçu corrigé le');

        $this->actingAs($secretariat)->delete(route('paiements.destroy', $paiement))->assertSessionHasErrors('motif');
        $this->actingAs($secretariat)->delete(route('paiements.destroy', $paiement), ['motif' => 'Paiement en double'])->assertRedirect();
        $this->assertNull(Paiement::find($paiement->id));

        // Journal : visible par la Direction seulement
        $this->actingAs($secretariat)->get(route('journal-financier.index'))->assertForbidden();
        $this->actingAs($this->utilisateur('direction'))->get(route('journal-financier.index'))->assertOk()
            ->assertSee('Montant mal saisi')->assertSee('Paiement en double')->assertSee($paiement->numero_recu);
    }

    public function test_numero_de_recu_selon_l_annee_scolaire(): void
    {
        $eleve = $this->eleve();
        $this->actingAs($this->utilisateur('secretariat'))->post('/paiements', [
            'eleve_id' => $eleve->id, 'types' => ['tranche1'], 'montant' => 5000, 'mode_paiement' => 'especes', 'date_paiement' => '2027-01-15',
        ])->assertRedirect();
        $this->assertSame('SMT-2026-0001', Paiement::firstOrFail()->numero_recu);
    }

    public function test_un_comptable_sans_cycle_n_a_aucun_acces(): void
    {
        $sansCycle = User::create(['name' => 'X', 'email' => 'x@test.td', 'password' => 'secret123', 'role' => 'comptable']);
        $this->assertFalse($sansCycle->peut('comptabilite'));
        $this->actingAs($sansCycle)->get(route('operations.index'))->assertForbidden();

        $this->actingAs($this->utilisateur('direction'))->post('/users', [
            'name' => 'Caisse', 'email' => 'caisse@test.td', 'password' => 'caisse2026', 'role' => 'comptable',
        ])->assertSessionHasErrors('cycle_id');
    }

    public function test_logistique_separee_par_cycle(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $comptable = $this->utilisateur('comptable', $primaire);
        $commun = Materiel::create(['nom' => 'Groupe électrogène', 'categorie' => 'Autre', 'quantite' => 1, 'etat' => 'bon', 'valeur_estimee' => 900000]);
        $bancsCollege = Materiel::create(['nom' => 'Bancs collège', 'categorie' => 'Mobilier', 'quantite' => 30, 'etat' => 'bon', 'valeur_estimee' => 15000, 'cycle_id' => $college->id]);

        $this->actingAs($comptable)->post(route('materiels.store'), ['nom' => 'Tableaux primaire', 'categorie' => 'Mobilier', 'quantite' => 4, 'etat' => 'bon', 'valeur_estimee' => 25000, 'cycle_id' => $college->id])->assertRedirect();
        $this->assertSame($primaire->id, Materiel::where('nom', 'Tableaux primaire')->value('cycle_id'));

        $this->actingAs($comptable)->get(route('materiels.index'))->assertOk()
            ->assertSee('Tableaux primaire')->assertDontSee('Bancs collège')->assertDontSee('Groupe électrogène');
        $this->actingAs($comptable)->get(route('materiels.edit', $bancsCollege))->assertForbidden();
        $this->actingAs($comptable)->delete(route('materiels.destroy', $commun))->assertForbidden();
        $this->actingAs($this->utilisateur('direction'))->get(route('materiels.index', ['cycle' => 'commun']))->assertOk()
            ->assertSee('Groupe électrogène')->assertDontSee('Tableaux primaire');
    }

    public function test_caisse_banque_et_versements(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $comptable = $this->utilisateur('comptable', $primaire);
        $eleve = $this->eleve($primaire);
        $this->paiement($eleve);                                    // 10 000 en espèces → caisse
        $this->paiement($this->eleve($college));                    // autre cycle : ignoré
        OperationFinanciere::create(['type' => 'depense', 'categorie' => 'Carburant', 'montant' => 2000, 'mode_paiement' => 'Espèces', 'date_operation' => now()->toDateString(), 'cycle_id' => $primaire->id]);
        OperationFinanciere::create(['type' => 'recette', 'categorie' => 'Don', 'montant' => 50000, 'mode_paiement' => 'Chèque', 'date_operation' => now()->toDateString(), 'cycle_id' => $primaire->id]);

        $this->actingAs($comptable)->post(route('tresorerie.soldes'), ['soldes' => ['caisse' => 5000, 'banque' => 100000, 'mobile' => 0]])->assertRedirect();
        $this->actingAs($comptable)->post(route('tresorerie.versements.store'), [
            'compte_source' => 'caisse', 'compte_destination' => 'banque', 'montant' => 8000, 'date_mouvement' => now()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($comptable)->post(route('tresorerie.versements.store'), [
            'compte_source' => 'caisse', 'compte_destination' => 'caisse', 'montant' => 1, 'date_mouvement' => now()->toDateString(),
        ])->assertSessionHasErrors('compte_destination');

        $reponse = $this->actingAs($comptable)->get(route('tresorerie.index'))->assertOk()->assertSee('Journal du');
        $comptes = $reponse->viewData('aujourdhui')['comptes'];
        $this->assertEquals(5000 + 10000 - 2000 - 8000, $comptes['caisse']['fin']);
        $this->assertEquals(100000 + 50000 + 8000, $comptes['banque']['fin']);
        $this->assertEquals(5000, $comptes['caisse']['debut']);

        // Correction du solde de départ : gardée au journal
        $this->actingAs($comptable)->post(route('tresorerie.soldes'), ['soldes' => ['caisse' => 6000, 'banque' => 100000, 'mobile' => 0]]);
        $this->assertTrue(JournalFinancier::where('objet', 'solde')->exists());

        // Un enseignant n'a pas accès ; la Direction voit toute l'école sans pouvoir y saisir
        $this->actingAs($this->utilisateur('enseignant'))->get(route('tresorerie.index'))->assertForbidden();
        $this->actingAs($this->utilisateur('direction'))->get(route('tresorerie.index'))->assertOk()->assertViewHas('saisiePossible', false);
    }
}

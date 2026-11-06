<?php

namespace Tests\Feature;

use App\Models\CarteRetrait;
use App\Models\ConfigurationEcole;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\FraisScolarite;
use App\Models\Paiement;
use App\Models\RelancePaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RelancesImpayesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20');
        ConfigurationEcole::actuelle()->update(['annee_scolaire_active' => '2026-2027']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Inscription 25 000 (sans date), tranche 1 : 50 000 au 31/10, tranche 2 au 31/01, tranche 3 au 30/04
    protected function echeancier(Cycle $cycle): FraisScolarite
    {
        return FraisScolarite::create([
            'cycle_id' => $cycle->id, 'annee_scolaire' => '2026-2027', 'montant_annuel' => 175000,
            'montant_inscription' => 25000,
            'montant_tranche1' => 50000, 'date_tranche1' => '2026-10-31',
            'montant_tranche2' => 50000, 'date_tranche2' => '2027-01-31',
            'montant_tranche3' => 50000, 'date_tranche3' => '2027-04-30',
        ]);
    }

    protected function payer(Eleve $eleve, float $montant, string $date = '2026-10-01'): void
    {
        Paiement::create([
            'eleve_id' => $eleve->id, 'annee_scolaire' => '2026-2027', 'types' => ['tranche1'], 'montant' => $montant,
            'mode_paiement' => 'especes', 'date_paiement' => $date, 'numero_recu' => Paiement::genererNumeroRecu(),
        ]);
    }

    public function test_le_montant_du_suit_l_echeancier(): void
    {
        $frais = $this->echeancier($this->cycle());

        $this->assertSame(25000.0, $frais->montantExigible(Carbon::parse('2026-10-01')));
        $this->assertSame(75000.0, $frais->montantExigible(Carbon::parse('2026-11-20')));
        $this->assertSame(175000.0, $frais->montantExigible(Carbon::parse('2027-05-01')));

        // Sans échéancier : tout le montant annuel est dû
        $simple = FraisScolarite::create(['cycle_id' => $this->cycle('Collège')->id, 'annee_scolaire' => '2026-2027', 'montant_annuel' => 90000]);
        $this->assertSame(90000.0, $simple->montantExigible());
    }

    public function test_les_freres_et_soeurs_sont_regroupes_et_seuls_les_retards_apparaissent(): void
    {
        $cycle = $this->cycle();
        $this->echeancier($cycle);

        $aine = $this->eleve($cycle, 'Aine');
        $cadet = $this->eleve($cycle, 'Cadet');
        $aine->update(['pere_nom' => 'Moussa Ali', 'pere_telephone' => '+235 66 12 34 56']);
        $cadet->update(['pere_nom' => 'Moussa Ali', 'pere_telephone' => '66 12 34 56']);   // même numéro, écrit autrement

        $cousin = $this->eleve($cycle, 'Cousin');      // lié à l'aîné par la carte de retrait
        $carte = CarteRetrait::create(['responsable_nom' => 'Tante', 'responsable_lien' => 'Tante', 'responsable_sexe' => 'F', 'responsable_telephone' => '1']);
        $carte->eleves()->attach([$aine->id, $cousin->id]);

        $aJour = $this->eleve($cycle, 'Ajour');
        $this->payer($aJour, 75000);
        $exonere = $this->eleve($cycle, 'Exonere');
        $exonere->update(['exonere' => true]);

        $this->payer($aine, 25000);

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('convocations.retards.index'))
            ->assertOk()
            ->assertSee('Familles en retard : <b>1</b>', false)
            ->assertSee('Moussa Ali')
            ->assertSee('Cadet')->assertSee('Cousin')
            ->assertSee('200 000 FCFA')          // 3 enfants × 75 000 dus à ce jour − 25 000 versés
            ->assertDontSee('Ajour')
            ->assertDontSee('Exonere')
            ->assertSee('1re relance');
    }

    public function test_les_relances_montent_de_niveau_puis_repartent_apres_un_paiement(): void
    {
        $cycle = $this->cycle();
        $this->echeancier($cycle);
        $eleve = $this->eleve($cycle, 'Moussa');
        $eleve->update(['pere_nom' => 'Moussa Ali']);
        $secretariat = $this->utilisateur('secretariat');

        $generer = fn (string $action = 'enregistrer') => $this->actingAs($secretariat)
            ->post(route('convocations.retards.generer'), ['familles' => [(string) $eleve->id], 'action' => $action, 'delai' => 8]);

        // Aperçu : rien n'est enregistré
        $generer('apercu')->assertOk()->assertSee('Rappel')->assertSee('ne sont pas enregistrées');
        $this->assertSame(0, RelancePaiement::count());

        $generer()->assertOk()->assertSee('Rappel — Scolarité 2026-2027')->assertSee('75 000 FCFA')->assertSee('28/11/2026');
        Carbon::setTestNow('2026-12-01');
        $generer()->assertOk()->assertSee('Deuxième relance')->assertSee('Malgré notre rappel du 20/11/2026');
        Carbon::setTestNow('2026-12-10');
        $generer()->assertOk()->assertSee('Convocation — Régularisation')->assertSee("vous présenter à l'administration", false);
        $generer()->assertOk()->assertSee('Convocation');   // reste au niveau convocation

        $this->assertSame([1, 2, 3, 3], RelancePaiement::orderBy('id')->pluck('niveau')->all());

        // La famille paie une partie : la lettre suivante repart au rappel
        $this->payer($eleve, 30000, '2026-12-12');
        Carbon::setTestNow('2026-12-15');
        $generer('apercu')->assertOk()->assertSee('Rappel — Scolarité')->assertSee('45 000 FCFA');

        $this->actingAs($secretariat)->get(route('convocations.retards.historique'))
            ->assertOk()->assertSee('Convocation')->assertSee('2e relance');
    }

    public function test_une_famille_a_jour_ne_recoit_pas_de_lettre(): void
    {
        $cycle = $this->cycle();
        $this->echeancier($cycle);
        $eleve = $this->eleve($cycle);
        $this->payer($eleve, 75000);

        $this->actingAs($this->utilisateur('secretariat'))
            ->from(route('convocations.retards.index'))
            ->post(route('convocations.retards.generer'), ['familles' => [(string) $eleve->id], 'action' => 'enregistrer'])
            ->assertRedirect(route('convocations.retards.index'))
            ->assertSessionHas('error');
        $this->assertSame(0, RelancePaiement::count());
    }

    public function test_un_utilisateur_limite_a_un_cycle_ne_relance_pas_les_autres(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $this->echeancier($college);
        $collegien = $this->eleve($college, 'Collegien');

        $secretariat = $this->utilisateur('secretariat', $primaire);
        $this->actingAs($secretariat)->get(route('convocations.retards.index'))->assertOk()->assertDontSee('Collegien');
        $this->actingAs($secretariat)->from(route('convocations.retards.index'))
            ->post(route('convocations.retards.generer'), ['familles' => [(string) $collegien->id], 'action' => 'enregistrer'])
            ->assertSessionHas('error');
        $this->assertSame(0, RelancePaiement::count());
    }

    public function test_reglage_de_l_echeancier_dans_les_frais(): void
    {
        $cycle = $this->cycle();

        $this->actingAs($this->utilisateur('secretariat'))
            ->post(route('frais.update'), ['frais' => [$cycle->id => [
                'montant_inscription' => 25000, 'date_inscription' => '',
                'montant_tranche1' => 50000, 'date_tranche1' => '2026-10-31',
                'montant_tranche2' => 50000, 'date_tranche2' => '2027-01-31',
                'montant_tranche3' => 50000, 'date_tranche3' => '2027-04-30',
                'montant_annuel' => 1,
            ]]])
            ->assertRedirect(route('frais.index'));

        $frais = FraisScolarite::first();
        $this->assertSame(175000.0, (float) $frais->montant_annuel);   // somme de l'échéancier
        $this->assertSame('2027-01-31', $frais->date_tranche2->format('Y-m-d'));

        // Montant annuel seul, sans échéancier
        $autre = $this->cycle('Collège');
        $this->actingAs($this->utilisateur('secretariat'))
            ->post(route('frais.update'), ['frais' => [$autre->id => ['montant_annuel' => 90000]]]);
        $this->assertSame(90000.0, (float) FraisScolarite::where('cycle_id', $autre->id)->value('montant_annuel'));

        $this->actingAs($this->utilisateur('secretariat'))->get(route('frais.index'))->assertOk()->assertSee('3e tranche');
    }
}

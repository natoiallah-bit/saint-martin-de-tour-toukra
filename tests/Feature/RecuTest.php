<?php

namespace Tests\Feature;

use App\Models\Paiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecuTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    public function test_le_recu_est_signe_par_le_comptable_qui_encaisse(): void
    {
        $eleve = $this->eleve();
        $comptable = $this->utilisateur('comptable', $eleve->classe->cycle);
        $comptable->update(['name' => 'Mme Fatimé ABAKAR']);

        $this->actingAs($comptable)->post('/paiements', [
            'eleve_id' => $eleve->id, 'types' => ['tranche1'], 'montant' => 10000, 'mode_paiement' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertRedirect();
        $paiement = Paiement::firstOrFail();
        $this->assertSame($comptable->id, $paiement->saisi_par);

        // Caisse : les deux exemplaires sur une page, une seule signature (le ou la comptable)
        $this->actingAs($comptable)
            ->get(route('paiements.recu', $paiement))
            ->assertOk()
            ->assertSee('Exemplaire Caisse')->assertSee('Exemplaire Parent')
            ->assertSee('Le / La Comptable')->assertSee('Mme Fatimé ABAKAR')
            ->assertDontSee('RAF')->assertDontSee('Caissier');

        // Parent : uniquement son exemplaire
        $this->actingAs($this->parentDe($eleve))
            ->get(route('paiements.recu', $paiement))
            ->assertOk()
            ->assertSee('Exemplaire Parent')
            ->assertDontSee('Exemplaire Caisse')
            ->assertSee('Imprimer mon reçu')
            ->assertSee('Mme Fatimé ABAKAR');
    }
}

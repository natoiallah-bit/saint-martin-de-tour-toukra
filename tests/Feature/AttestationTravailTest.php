<?php

namespace Tests\Feature;

use App\Helpers\QrAuthenticite;
use App\Models\AttestationTravail;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttestationTravailTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    private function membre(array $attributs = []): Personnel
    {
        return Personnel::create($attributs + [
            'matricule' => 'PERS-0001', 'nom' => 'OUMAR', 'prenom' => 'Hawa', 'type' => 'enseignant', 'fonction' => 'Institutrice',
            'salaire_base' => 90000, 'actif' => true, 'statut' => 'permanent', 'date_embauche' => '2019-09-02',
        ]);
    }

    public function test_la_liste_n_affiche_que_fiche_et_la_fiche_regroupe_les_actions(): void
    {
        $p = $this->membre();
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->get(route('personnels.index'))->assertOk()
            ->assertSee(route('personnels.show', $p), false)
            ->assertDontSee(route('personnels.badge', $p), false)
            ->assertDontSee(route('personnels.edit', $p), false);

        $this->actingAs($secretariat)->get(route('personnels.show', $p))->assertOk()
            ->assertSee(route('personnels.badge', $p), false)
            ->assertSee(route('personnels.documents.index', $p), false)
            ->assertSee(route('personnels.edit', $p), false)
            ->assertSee(route('paies.create', ['personnel_id' => $p->id]), false)
            ->assertSee('Supprimer')
            ->assertSee(route('attestations.store', $p), false);

        // Le principal voit la fiche (personnel de son cycle) mais aucune action de gestion
        $cycle = $this->cycle('Primaire');
        $p->update(['cycle_id' => $cycle->id]);
        $this->actingAs($this->utilisateur('principal', $cycle))->get(route('personnels.show', $p))->assertOk()
            ->assertDontSee(route('personnels.edit', $p), false)
            ->assertDontSee(route('attestations.store', $p), false)
            ->assertDontSee('Supprimer');

        // La paie est présélectionnée en arrivant depuis la fiche
        $this->actingAs($secretariat)->get(route('paies.create', ['personnel_id' => $p->id]))->assertOk()
            ->assertSee('value="' . $p->id . '" selected', false);
    }

    public function test_attestation_de_travail_delivree_verifiable_et_annulable(): void
    {
        $p = $this->membre();
        $secretariat = $this->utilisateur('secretariat');

        $reponse = $this->actingAs($secretariat)->post(route('attestations.store', $p));
        $attestation = AttestationTravail::firstOrFail();
        $reponse->assertRedirect(route('attestations.imprimer', $attestation));
        $this->assertSame('AT-' . now()->format('Y') . '-0001', $attestation->numero);

        $this->actingAs($secretariat)->get(route('attestations.imprimer', $attestation))->assertOk()
            ->assertSee('CERTIFICAT DE TRAVAIL')
            ->assertSee('OUMAR Hawa')
            ->assertSee('2 septembre 2019')
            ->assertSee('Institutrice')
            ->assertDontSee('Motif de la demande');

        $this->actingAs($secretariat)->get(route('personnels.show', $p))->assertSee($attestation->numero);

        // Ni le comptable ni l'enseignant ne délivrent d'attestation
        $this->actingAs($this->utilisateur('comptable'))->post(route('attestations.store', $p))->assertForbidden();
        $this->actingAs($this->utilisateur('enseignant'))->get(route('attestations.imprimer', $attestation))->assertForbidden();

        $lien = QrAuthenticite::lien('verification.attestation', $attestation->numero);
        auth()->logout();
        $this->get($lien)->assertOk()->assertSee('Document authentique')->assertSee('Institutrice');

        $this->actingAs($secretariat)->delete(route('attestations.destroy', $attestation))->assertRedirect(route('personnels.show', $p));
        auth()->logout();
        $this->get($lien)->assertOk()->assertSee('introuvable');
    }
}

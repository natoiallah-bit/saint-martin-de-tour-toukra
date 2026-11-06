<?php

namespace Tests\Feature;

use App\Helpers\QrAuthenticite;
use App\Models\CertificatScolarite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatScolariteTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    public function test_le_secretariat_delivre_un_certificat_visible_par_le_parent(): void
    {
        $eleve = $this->eleve(null, 'ABAKAR');
        $eleve->update(['prenom' => 'Fatimé', 'sexe' => 'F', 'date_naissance' => '2014-03-05', 'lieu_naissance' => "N'Djamena"]);
        $secretariat = $this->utilisateur('secretariat');

        $reponse = $this->actingAs($secretariat)->post(route('certificats.store', $eleve));
        $certificat = CertificatScolarite::firstOrFail();
        $reponse->assertRedirect(route('certificats.imprimer', $certificat));

        $this->assertSame('CERT-' . now()->format('Y') . '-0001', $certificat->numero);
        $this->assertSame($eleve->classe->nom, $certificat->classe_nom);

        $this->actingAs($secretariat)->get(route('certificats.imprimer', $certificat))->assertOk()
            ->assertSee('CERTIFICAT D\'INSCRIPTION', false)
            ->assertSee('ABAKAR Fatimé')
            ->assertSee('née le')
            ->assertDontSee('Motif de la demande');

        // Le parent le retrouve dans son espace et peut l'imprimer
        $parent = $this->parentDe($eleve);
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()
            ->assertSee($certificat->numero)
            ->assertSee(route('certificats.imprimer', $certificat), false);
        $this->actingAs($parent)->get(route('certificats.imprimer', $certificat))->assertOk();

        // Un autre parent n'y a pas accès
        $autre = $this->parentDe($this->eleve(null, 'AUTRE'));
        $this->actingAs($autre)->get(route('certificats.imprimer', $certificat))->assertForbidden();
        $this->actingAs($autre)->post(route('certificats.store', $eleve))->assertForbidden();

        // Deuxième certificat : numéro suivant
        $this->actingAs($secretariat)->post(route('certificats.store', $eleve));
        $this->assertTrue(CertificatScolarite::where('numero', 'CERT-' . now()->format('Y') . '-0002')->exists());
    }

    public function test_droits_et_verification_par_qr_code(): void
    {
        $eleve = $this->eleve();
        $this->actingAs($this->utilisateur('enseignant'))->post(route('certificats.store', $eleve))->assertForbidden();

        $certificat = CertificatScolarite::create([
            'eleve_id' => $eleve->id, 'numero' => 'CS-2026-0042', 'annee_scolaire' => '2026-2027',
            'classe_nom' => 'CM2', 'date_delivrance' => '2026-10-14',
        ]);

        $lien = QrAuthenticite::lien('verification.certificat', $certificat->numero);
        $this->get($lien)->assertOk()->assertSee('Document authentique')->assertSee('CM2');

        // Annulé par le secrétariat : le QR code ne le reconnaît plus
        $this->actingAs($this->utilisateur('secretariat'))->delete(route('certificats.destroy', $certificat))
            ->assertRedirect(route('recherche.eleve', $eleve));
        auth()->logout();
        $this->get($lien)->assertOk()->assertSee('introuvable');
    }
}

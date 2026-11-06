<?php

namespace App\Http\Controllers;

use App\Models\AttestationTravail;
use App\Models\ConfigurationEcole;
use App\Models\Personnel;
use Illuminate\Http\Request;

class AttestationTravailController extends Controller
{
    // Délivre une attestation depuis la fiche du membre du personnel
    public function store(Request $request, Personnel $personnel)
    {

        $attestation = AttestationTravail::create([
            'personnel_id' => $personnel->id,
            'numero' => AttestationTravail::prochainNumero(),
            'fonction' => $personnel->fonction ?: $personnel->libelle_type,
            'statut' => $personnel->statut,
            'date_embauche' => $personnel->date_embauche,
            'en_poste' => (bool) $personnel->actif,
            'date_delivrance' => now()->toDateString(),
            'delivre_par' => auth()->id(),
        ]);

        return redirect()->route('attestations.imprimer', $attestation)->with('success', 'Certificat de travail délivrée.');
    }

    public function imprimer(AttestationTravail $attestation)
    {
        return view('attestations.imprimer', [
            'attestation' => $attestation->load('personnel'),
            'personnel' => $attestation->personnel,
            'config' => ConfigurationEcole::actuelle(),
        ]);
    }

    // Annulation d'une attestation délivrée par erreur
    public function destroy(AttestationTravail $attestation)
    {
        $personnel = $attestation->personnel;
        $attestation->delete();

        return redirect()->route('personnels.show', $personnel)->with('success', 'Certificat de travail annulé.');
    }
}

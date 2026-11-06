<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Models\CertificatScolarite;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use Illuminate\Http\Request;

class CertificatScolariteController extends Controller
{
    // Délivre un certificat depuis la fiche de l'élève : il apparaît aussitôt dans l'espace du parent
    public function store(Request $request, Eleve $eleve)
    {
        PorteeCycle::verifierEleve($eleve);
        abort_unless($eleve->classe_id, 422, "L'élève n'est inscrit dans aucune classe.");

        $eleve->load('classe');

        $certificat = CertificatScolarite::create([
            'eleve_id' => $eleve->id,
            'numero' => CertificatScolarite::prochainNumero(),
            'annee_scolaire' => ConfigurationEcole::anneeActive(),
            'classe_nom' => $eleve->classe->nom,
            'date_delivrance' => now()->toDateString(),
            'delivre_par' => auth()->id(),
        ]);

        return redirect()->route('certificats.imprimer', $certificat)
            ->with('success', 'Certificat délivré : il est aussi disponible dans l\'espace du parent.');
    }

    // Certificat imprimable : personnel autorisé ou parent de l'élève
    public function imprimer(CertificatScolarite $certificat)
    {
        $user = auth()->user();
        $certificat->load('eleve.classe.cycle');

        if ($user->role === 'parent') {
            abort_unless($user->estParentDe($certificat->eleve_id), 403, "Ce certificat ne concerne pas l'un de vos enfants.");
        } else {
            PorteeCycle::verifierEleve($certificat->eleve);
        }

        return view('certificats.imprimer', [
            'certificat' => $certificat,
            'eleve' => $certificat->eleve,
            'config' => ConfigurationEcole::actuelle(),
        ]);
    }

    // Annulation d'un certificat délivré par erreur
    public function destroy(CertificatScolarite $certificat)
    {
        PorteeCycle::verifierEleve($certificat->eleve);
        $eleve = $certificat->eleve;
        $certificat->delete();

        return redirect()->route('recherche.eleve', $eleve)->with('success', 'Certificat annulé.');
    }
}

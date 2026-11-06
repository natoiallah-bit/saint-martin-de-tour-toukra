<?php

namespace App\Http\Controllers;

use App\Helpers\Fichier;
use App\Helpers\PorteeCycle;

use App\Models\DocumentEleve;
use App\Models\Eleve;
use Illuminate\Http\Request;

class DocumentEleveController extends Controller
{
    // Liste des documents d'un élève précis
    public function index(Eleve $eleve)
    {
        PorteeCycle::verifierEleve($eleve);

        $documents = $eleve->documents()->orderByDesc('created_at')->get();
        return view('eleves.documents', compact('eleve', 'documents'));
    }

    public function store(Request $request, Eleve $eleve)
    {
        PorteeCycle::verifierEleve($eleve);

        $data = $request->validate([
            'type' => 'required|string|max:100',
            'fichier' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5 Mo max
        ]);

        $fichier = $request->file('fichier');
        $nomOriginal = $fichier->getClientOriginalName();
        $chemin = Fichier::enregistrer($fichier, 'documents_eleves', 'doc_');

        DocumentEleve::create([
            'eleve_id' => $eleve->id,
            'type' => $data['type'],
            'nom_fichier' => $nomOriginal,
            'chemin_fichier' => $chemin,
        ]);

        return redirect()->route('eleves.documents.index', $eleve)->with('success', 'Document ajouté.');
    }

    public function destroy(Eleve $eleve, DocumentEleve $document)
    {
        PorteeCycle::verifierEleve($eleve);

        abort_unless((int) $document->eleve_id === (int) $eleve->id, 404);

        Fichier::supprimer($document->chemin_fichier);
        $document->delete();

        return redirect()->route('eleves.documents.index', $eleve)->with('success', 'Document supprimé.');
    }
}

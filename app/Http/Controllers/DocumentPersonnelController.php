<?php

namespace App\Http\Controllers;

use App\Helpers\Fichier;

use App\Models\DocumentPersonnel;
use App\Models\Personnel;
use Illuminate\Http\Request;

class DocumentPersonnelController extends Controller
{
    public function index(Personnel $personnel)
    {
        $documents = $personnel->documentsPersonnel()->latest()->get();
        return view('personnels.documents', compact('personnel', 'documents'));
    }

    public function store(Request $request, Personnel $personnel)
    {
        $data = $request->validate([
            'type' => 'required|string|max:100',
            'fichier' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $fichier = $request->file('fichier');
        $nomOriginal = $fichier->getClientOriginalName();
        $chemin = Fichier::enregistrer($fichier, 'documents_personnel', 'doc_');

        DocumentPersonnel::create([
            'personnel_id' => $personnel->id,
            'type' => $data['type'],
            'nom_fichier' => $nomOriginal,
            'chemin_fichier' => $chemin,
        ]);

        return redirect()->route('personnels.documents.index', $personnel)->with('success', 'Document ajouté.');
    }

    public function destroy(Personnel $personnel, DocumentPersonnel $document)
    {
        abort_unless((int) $document->personnel_id === (int) $personnel->id, 404);

        Fichier::supprimer($document->chemin_fichier);
        $document->delete();

        return redirect()->route('personnels.documents.index', $personnel)->with('success', 'Document supprimé.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Models\ClasseScolaire;
use App\Models\Cycle;
use Illuminate\Http\Request;

class ClasseScolaireController extends Controller
{
    public function index()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $classes = ClasseScolaire::with('cycle', 'profPrincipal', 'professeurPrincipal')->withCount('eleves')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->ordonnees()->get();
        return view('classes.index', compact('classes'));
    }

    public function create()
    {
        $cycles = $this->cyclesAutorises();
        $enseignants = $this->enseignants();
        return view('classes.create', compact('cycles', 'enseignants'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['required', \Illuminate\Validation\Rule::in($this->cyclesAutorises()->pluck('id')->all())],
            'nom' => 'required|string|max:50',
            'prof_principal_personnel_id' => $this->reglePersonnel(),
        ]);

        ClasseScolaire::create($data);

        return redirect()->route('classes.index')->with('success', 'Classe ajoutée.');
    }

    public function edit($id)
    {
        $classe = ClasseScolaire::findOrFail($id);
        PorteeCycle::verifierClasse($classe);
        $cycles = $this->cyclesAutorises();
        $enseignants = $this->enseignants();
        // Ceux qui ont cours dans la classe (emploi du temps) sont proposés en premier
        $enseignantsDeLaClasse = \App\Models\EmploiTemps::where('classe_id', $classe->id)->pluck('enseignant_id')->filter()->unique()->all();
        return view('classes.edit', compact('classe', 'cycles', 'enseignants', 'enseignantsDeLaClasse'));
    }

    public function update(Request $request, $id)
    {
        $classe = ClasseScolaire::findOrFail($id);
        PorteeCycle::verifierClasse($classe);

        $data = $request->validate([
            'cycle_id' => ['required', \Illuminate\Validation\Rule::in($this->cyclesAutorises()->pluck('id')->all())],
            'nom' => 'required|string|max:50',
            'prof_principal_personnel_id' => $this->reglePersonnel(),
        ]);

        // Le choix fait ici remplace celui de l'ancienne liste « Enseignants »
        $classe->update($data + ['prof_principal_id' => null]);

        return redirect()->route('classes.index')->with('success', 'Classe mise à jour.');
    }

    public function destroy($id)
    {
        $classe = ClasseScolaire::findOrFail($id);
        PorteeCycle::verifierClasse($classe);

        if ($classe->eleves()->count() > 0) {
            return back()->withErrors(['general' => 'Impossible de supprimer : des élèves sont encore inscrits dans cette classe.']);
        }
        $classe->delete();
        return redirect()->route('classes.index')->with('success', 'Classe supprimée.');
    }

    // Liste des enseignants regroupés par cycle, déduite des matières qu'ils enseignent,
    // pour proposer le bon choix de "professeur principal" selon le cycle de la classe.
    // Professeurs principaux possibles : le Personnel de type Enseignant encore en poste
    private function enseignants()
    {
        return \App\Models\Personnel::where('type', 'enseignant')->where('actif', true)
            ->orderBy('nom')->orderBy('prenom')->get(['id', 'nom', 'prenom', 'cycle_id']);
    }

    private function reglePersonnel()
    {
        return ['nullable', \Illuminate\Validation\Rule::exists('personnels', 'id')->where('type', 'enseignant')];
    }

    // Un Principal ne gère que les classes de son cycle ; la Direction choisit le cycle
    private function cyclesAutorises()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return Cycle::when($cycleId, fn ($q) => $q->where('id', $cycleId))->get();
    }
}

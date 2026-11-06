<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Models\Matiere;
use App\Models\Cycle;
use Illuminate\Http\Request;

class MatiereGestionController extends Controller
{
    public function index()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $matieres = Matiere::with('cycle')->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->orderBy('nom')->get();
        return view('matieres.index', compact('matieres'));
    }

    public function create()
    {
        $cycles = $this->cyclesAutorises();
        return view('matieres.create', compact('cycles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['required', \Illuminate\Validation\Rule::in($this->cyclesAutorises()->pluck('id')->all())],
            'nom' => 'required|string|max:100',
            'coefficient' => 'nullable|integer|min:1|max:10',
            'categorie' => 'nullable|string|max:50',
        ]);

        // Sans ce droit, la matière est créée avec le coefficient 1 ; le Principal l'ajustera
        $peutFixer = Matiere::peutFixerCoefficient(auth()->user(), (int) $data['cycle_id']);
        $data['coefficient'] = $peutFixer && ! empty($data['coefficient']) ? $data['coefficient'] : 1;

        Matiere::create($data);

        return redirect()->route('matieres-gestion.index')->with('success', 'Matière ajoutée.');
    }

    public function edit(Matiere $matieres_gestion)
    {
        $matiere = $matieres_gestion;
        PorteeCycle::verifierCycle($matiere->cycle_id);
        $cycles = $this->cyclesAutorises();
        return view('matieres.edit', compact('matiere', 'cycles'));
    }

    public function update(Request $request, Matiere $matieres_gestion)
    {
        $matiere = $matieres_gestion;
        PorteeCycle::verifierCycle($matiere->cycle_id);

        $data = $request->validate([
            'cycle_id' => ['required', \Illuminate\Validation\Rule::in($this->cyclesAutorises()->pluck('id')->all())],
            'nom' => 'required|string|max:100',
            'coefficient' => 'nullable|integer|min:1|max:10',
            'categorie' => 'nullable|string|max:50',
        ]);

        // Seuls la Direction et le Principal du cycle changent le coefficient (ancien et nouveau cycle)
        $peutFixer = Matiere::peutFixerCoefficient(auth()->user(), $matiere->cycle_id)
            && Matiere::peutFixerCoefficient(auth()->user(), (int) $data['cycle_id']);
        if (! $peutFixer || empty($data['coefficient'])) {
            unset($data['coefficient']);
        }

        $matiere->update($data);

        return redirect()->route('matieres-gestion.index')->with('success', 'Matière mise à jour.');
    }

    public function destroy(Matiere $matieres_gestion)
    {
        PorteeCycle::verifierCycle($matieres_gestion->cycle_id);
        // Supprimer la matière effacerait toutes ses notes et ses devoirs
        if (\App\Models\Note::toutesAnnees()->where('matiere_id', $matieres_gestion->id)->exists()
            || \App\Models\Evaluation::toutesAnnees()->where('matiere_id', $matieres_gestion->id)->exists()) {
            return back()->withErrors(['general' => 'Impossible de supprimer : des notes ou des devoirs existent déjà pour cette matière.']);
        }
        $matieres_gestion->delete();
        return redirect()->route('matieres-gestion.index')->with('success', 'Matière supprimée.');
    }

    // Un Principal ne gère que les matières de son cycle ; la Direction choisit le cycle
    private function cyclesAutorises()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return Cycle::when($cycleId, fn ($q) => $q->where('id', $cycleId))->get();
    }
}

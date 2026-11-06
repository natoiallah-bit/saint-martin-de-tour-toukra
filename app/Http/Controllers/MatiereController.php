<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Enseignant;
use App\Models\Matiere;
use Illuminate\Http\Request;

class MatiereController extends Controller
{
    protected function rules(): array
    {
        return [
            'cycle_id' => 'required|exists:cycles,id',
            'enseignant_id' => 'nullable|exists:enseignants,id',
            'nom' => 'required|string|max:100',
            'coefficient' => 'required|integer|min:1|max:10',
            'categorie' => 'required|in:Scientifique,Littéraire,Accessoire',
        ];
    }

    public function index()
    {
        $matieres = Matiere::with(['cycle', 'enseignant'])->orderBy('cycle_id')->orderBy('categorie')->get();
        return view('matieres.index', compact('matieres'));
    }

    public function create()
    {
        $cycles = Cycle::all();
        $enseignants = Enseignant::orderBy('nom')->get();
        return view('matieres.create', compact('cycles', 'enseignants'));
    }

    public function store(Request $request)
    {
        Matiere::create($request->validate($this->rules()));
        return redirect()->route('matieres.index')->with('success', 'Matière ajoutée.');
    }

    public function edit(Matiere $matiere)
    {
        $cycles = Cycle::all();
        $enseignants = Enseignant::orderBy('nom')->get();
        return view('matieres.edit', compact('matiere', 'cycles', 'enseignants'));
    }

    public function update(Request $request, Matiere $matiere)
    {
        $matiere->update($request->validate($this->rules()));
        return redirect()->route('matieres.index')->with('success', 'Matière mise à jour.');
    }

    public function destroy(Matiere $matiere)
    {
        $matiere->delete();
        return redirect()->route('matieres.index')->with('success', 'Matière supprimée.');
    }
}

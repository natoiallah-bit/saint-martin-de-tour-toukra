<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Models\Cycle;
use App\Models\Materiel;
use Illuminate\Http\Request;

class MaterielController extends Controller
{
    public function index(Request $request)
    {
        // Un comptable ne voit que le matériel de son cycle ; la Direction voit tout (ou un cycle, ou le commun)
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $filtreCycle = $cycleId ?: ($request->filled('cycle') ? $request->cycle : null);
        $portee = fn ($q) => \App\Helpers\Tresorerie::filtrerPortee($q, $filtreCycle);

        $query = Materiel::with('cycle')->where($portee)->orderBy('categorie');
        if ($request->filled('categorie')) {
            $query->where('categorie', $request->categorie);
        }
        $materiels = $query->paginate(20)->withQueryString();

        // Valeur totale du parc, et regroupement par catégorie
        $tous = Materiel::where($portee)->get();
        $valeurTotale = $tous->sum(fn($m) => $m->valeur_totale);
        $parCategorie = $tous->groupBy('categorie')->map(function ($groupe) {
            return [
                'quantite' => $groupe->sum('quantite'),
                'valeur' => $groupe->sum(fn($m) => $m->valeur_totale),
            ];
        });

        return view('materiels.index', compact('materiels', 'valeurTotale', 'parCategorie', 'filtreCycle') + [
            'cycles' => $cycleId ? collect() : Cycle::orderBy('nom')->get(),
            'cycleImpose' => $cycleId ? Cycle::find($cycleId) : null,
        ]);
    }

    public function create()
    {
        return view('materiels.create', ['categories' => Materiel::categories(), 'cycles' => $this->cyclesAuChoix()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:150',
            'categorie' => 'required|string|max:100',
            'quantite' => 'required|integer|min:0',
            'etat' => 'required|in:bon,moyen,mauvais,hors_service',
            'valeur_estimee' => 'required|numeric|min:0',
            'localisation' => 'nullable|string|max:150',
            'date_acquisition' => 'nullable|date',
            'remarque' => 'nullable|string|max:255',
            'cycle_id' => 'nullable|integer|exists:cycles,id',
        ]);

        $data['cycle_id'] = PorteeCycle::pourUtilisateurConnecte() ?: ($data['cycle_id'] ?? null);
        Materiel::create($data);

        return redirect()->route('materiels.index')->with('success', 'Matériel ajouté.');
    }

    public function edit(Materiel $materiel)
    {
        PorteeCycle::verifierCycle($materiel->cycle_id);

        return view('materiels.edit', ['materiel' => $materiel, 'categories' => Materiel::categories(), 'cycles' => $this->cyclesAuChoix()]);
    }

    public function update(Request $request, Materiel $materiel)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:150',
            'categorie' => 'required|string|max:100',
            'quantite' => 'required|integer|min:0',
            'etat' => 'required|in:bon,moyen,mauvais,hors_service',
            'valeur_estimee' => 'required|numeric|min:0',
            'localisation' => 'nullable|string|max:150',
            'date_acquisition' => 'nullable|date',
            'remarque' => 'nullable|string|max:255',
            'cycle_id' => 'nullable|integer|exists:cycles,id',
        ]);

        PorteeCycle::verifierCycle($materiel->cycle_id);
        $data['cycle_id'] = PorteeCycle::pourUtilisateurConnecte() ?: ($data['cycle_id'] ?? null);
        $materiel->update($data);

        return redirect()->route('materiels.index')->with('success', 'Fiche matériel mise à jour.');
    }

    public function destroy(Materiel $materiel)
    {
        PorteeCycle::verifierCycle($materiel->cycle_id);
        $materiel->delete();
        return redirect()->route('materiels.index')->with('success', 'Matériel retiré.');
    }

    // La Direction choisit le cycle du matériel ; un comptable ne gère que le sien
    private function cyclesAuChoix()
    {
        return PorteeCycle::pourUtilisateurConnecte() ? collect() : Cycle::orderBy('nom')->get();
    }
}

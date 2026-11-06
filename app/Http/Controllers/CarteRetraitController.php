<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Models\CarteRetrait;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Cartes de retrait des enfants : une carte par famille, avec 1 ou 2 personnes
// autorisées à venir chercher les enfants à la sortie de l'école.
class CarteRetraitController extends Controller
{
    public function index(Request $request)
    {
        $recherche = trim((string) $request->get('q'));

        $cartes = $this->cartesVisibles()
            ->with('eleves.classe')
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    $q->where('numero', 'like', "%{$recherche}%")
                        ->orWhere('responsable_nom', 'like', "%{$recherche}%")
                        ->orWhere('pere_nom', 'like', "%{$recherche}%")
                        ->orWhere('mere_nom', 'like', "%{$recherche}%")
                        ->orWhere('second_nom', 'like', "%{$recherche}%")
                        ->orWhereHas('eleves', fn ($e) => $e->where('nom', 'like', "%{$recherche}%")->orWhere('prenom', 'like', "%{$recherche}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cartes-retrait.index', compact('cartes', 'recherche'));
    }

    public function create()
    {
        return view('cartes-retrait.form', ['carte' => new CarteRetrait(['responsable_sexe' => 'F']), 'eleves' => $this->elevesDisponibles()]);
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);

        $carte = CarteRetrait::create($donnees);
        $carte->update(['numero' => 'RET-' . str_pad($carte->id, 4, '0', STR_PAD_LEFT)]);
        $carte->eleves()->sync($request->input('eleves'));

        return redirect()->route('cartes-retrait.index')->with('success', "Carte {$carte->numero} créée.");
    }

    public function edit(CarteRetrait $carte)
    {
        $this->verifierCarte($carte);

        return view('cartes-retrait.form', ['carte' => $carte->load('eleves'), 'eleves' => $this->elevesDisponibles()]);
    }

    public function update(Request $request, CarteRetrait $carte)
    {
        $this->verifierCarte($carte);
        $donnees = $this->valider($request);

        $carte->update($donnees);
        // Les enfants d'un autre cycle (invisibles dans le formulaire) restent sur la carte de la famille
        $visibles = $this->elevesDisponibles()->pluck('id');
        $horsPortee = $carte->eleves()->pluck('eleves.id')->diff($visibles);
        $carte->eleves()->sync(collect($request->input('eleves', []))->map(fn ($id) => (int) $id)->intersect($visibles)->merge($horsPortee)->unique()->values()->all());

        return redirect()->route('cartes-retrait.index')->with('success', "Carte {$carte->numero} modifiée.");
    }

    public function destroy(CarteRetrait $carte)
    {
        $this->verifierCarte($carte);
        $carte->delete();

        return redirect()->route('cartes-retrait.index')->with('success', 'Carte supprimée.');
    }

    // Impression d'une seule carte
    public function imprimer(CarteRetrait $carte)
    {
        $this->verifierCarte($carte);

        return view('cartes.page', [
            'titre' => 'Carte de retrait ' . $carte->numero,
            'retour' => route('cartes-retrait.index'),
            'config' => ConfigurationEcole::actuelle(),
            'cartesRetrait' => collect([$carte->load('eleves.classe.cycle')]),
        ]);
    }

    // Impression de toutes les cartes (dans la limite du cycle de l'utilisateur)
    public function imprimerTout()
    {
        $cartes = $this->cartesVisibles()->with('eleves.classe.cycle')->orderBy('id')->get();
        abort_if($cartes->isEmpty(), 404, 'Aucune carte de retrait à imprimer.');

        return view('cartes.page', [
            'titre' => 'Cartes de retrait (' . $cartes->count() . ')',
            'retour' => route('cartes-retrait.index'),
            'config' => ConfigurationEcole::actuelle(),
            'cartesRetrait' => $cartes,
        ]);
    }

    private function valider(Request $request): array
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $liens = Rule::in(CarteRetrait::LIENS);

        $request->validate([
            'pere_nom' => 'nullable|required_without:mere_nom|string|max:150',
            'pere_telephone' => 'nullable|string|max:30',
            'mere_nom' => 'nullable|string|max:150',
            'mere_telephone' => 'nullable|string|max:30',
            'quartier' => 'nullable|string|max:100',
            'responsable_nom' => 'required|string|max:150',
            'responsable_lien' => ['required', $liens],
            'responsable_sexe' => 'required|in:F,M',
            'responsable_telephone' => 'required|string|max:30',
            'responsable_piece' => 'nullable|string|max:60',
            'second_nom' => 'nullable|string|max:150',
            'second_lien' => ['nullable', 'required_with:second_nom', $liens],
            'second_telephone' => 'nullable|required_with:second_nom|string|max:30',
            'second_piece' => 'nullable|string|max:60',
            'eleves' => 'required|array|min:1',
            'eleves.*' => ['integer', 'distinct', Rule::exists('eleves', 'id')->when($cycleId, fn ($r) => $r->whereIn('classe_id', \App\Models\ClasseScolaire::where('cycle_id', $cycleId)->pluck('id')))],
        ], [
            'eleves.required' => 'Cochez au moins un enfant.',
            'pere_nom.required_without' => 'Indiquez le nom du père ou de la mère.',
            'second_lien.required_with' => 'Indiquez le lien de la deuxième personne.',
            'second_telephone.required_with' => 'Indiquez le téléphone de la deuxième personne.',
        ]);

        $donnees = $request->only((new CarteRetrait)->getFillable());
        unset($donnees['numero']);

        // Pas de deuxième personne : on vide tous ses champs
        if (blank($request->second_nom)) {
            $donnees = array_merge($donnees, ['second_nom' => null, 'second_lien' => null, 'second_telephone' => null, 'second_piece' => null]);
        }

        return $donnees;
    }

    // Cartes dont au moins un enfant appartient au cycle de l'utilisateur
    private function cartesVisibles()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return CarteRetrait::query()->when($cycleId, fn ($q) => $q->whereHas('eleves.classe', fn ($c) => $c->where('cycle_id', $cycleId)));
    }

    private function verifierCarte(CarteRetrait $carte): void
    {
        abort_unless($this->cartesVisibles()->whereKey($carte->id)->exists() || $carte->eleves()->doesntExist(), 403, 'Cette carte ne concerne pas votre cycle.');
    }

    private function elevesDisponibles()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return Eleve::with('classe')
            ->when($cycleId, fn ($q) => $q->whereHas('classe', fn ($c) => $c->where('cycle_id', $cycleId)))
            ->orderBy('nom')->orderBy('prenom')
            ->get();
    }
}

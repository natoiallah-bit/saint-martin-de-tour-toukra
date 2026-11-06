<?php

namespace App\Http\Controllers;

use App\Helpers\Fichier;
use App\Helpers\PorteeCycle;
use App\Models\ConfigurationEcole;
use App\Models\Cycle;
use App\Models\JournalFinancier;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use Illuminate\Http\Request;

class ComptabiliteController extends Controller
{
    // Champs gardés au journal quand une opération est corrigée ou supprimée
    private const CHAMPS_JOURNAL = ['type', 'categorie', 'montant', 'mode_paiement', 'date_operation', 'description', 'cycle_id'];

    public function index(Request $request)
    {
        $anneeScolaire = ConfigurationEcole::anneeActive();
        // Un comptable ne voit que son cycle ; la Direction voit tout (ou un cycle, ou le commun)
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $filtreCycle = $cycleId ?: ($request->filled('cycle') ? $request->cycle : null);
        $mois = $request->filled('mois') && preg_match('/^\d{4}-\d{2}$/', $request->mois) ? $request->mois : null;

        $portee = function ($q) use ($filtreCycle) {
            if ($filtreCycle === 'commun') {
                $q->whereNull('cycle_id');
            } elseif ($filtreCycle) {
                $q->where('cycle_id', $filtreCycle);
            }
        };
        $periode = function ($q, string $colonne) use ($mois) {
            $q->when($mois, fn ($qq) => $qq->whereYear($colonne, substr($mois, 0, 4))->whereMonth($colonne, substr($mois, 5, 2)));
        };

        // Même période partout : l'année scolaire active, et le mois choisi s'il y en a un
        $query = OperationFinanciere::with(['cycle', 'auteur'])->where('annee_scolaire', $anneeScolaire)
            ->where($portee)->where(fn ($q) => $periode($q, 'date_operation'));
        $operations = (clone $query)->orderByDesc('date_operation')->orderByDesc('id')->paginate(20)->withQueryString();
        $toutes = (clone $query)->get();

        // Paiements de scolarité (enregistrés dans le module Scolarité) du cycle et de la période choisis
        $totalScolarite = $filtreCycle === 'commun' ? 0 : Paiement::where('annee_scolaire', $anneeScolaire)
            ->when($filtreCycle, fn ($q) => $q->whereHas('eleve.classe', fn ($c) => $c->where('cycle_id', $filtreCycle)))
            ->where(fn ($q) => $periode($q, 'date_paiement'))
            ->sum('montant');

        $recettesParCategorie = $toutes->where('type', 'recette')->groupBy('categorie')->map(fn ($g) => $g->sum('montant'));
        $depensesParCategorie = $toutes->where('type', 'depense')->groupBy('categorie')->map(fn ($g) => $g->sum('montant'));

        $totalRecettesManuelles = $toutes->where('type', 'recette')->sum('montant');
        $totalDepenses = $toutes->where('type', 'depense')->sum('montant');

        // Recettes totales = paiements scolarité + recettes manuelles (dons, subventions...)
        $totalRecettes = $totalScolarite + $totalRecettesManuelles;
        $solde = $totalRecettes - $totalDepenses;

        // Salaires saisis à la main avant l'ajout automatique : risque de double comptage
        $salairesManuels = $toutes->where('type', 'depense')->where('categorie', 'Salaires')->whereNull('paie_id')->count();

        return view('operations.index', compact(
            'operations', 'totalScolarite', 'totalRecettesManuelles', 'totalDepenses',
            'totalRecettes', 'solde', 'recettesParCategorie', 'depensesParCategorie', 'salairesManuels', 'anneeScolaire', 'mois'
        ) + ['cycles' => $cycleId ? collect() : Cycle::orderBy('nom')->get(), 'cycleImpose' => $cycleId ? Cycle::find($cycleId) : null, 'filtreCycle' => $filtreCycle]);
    }

    public function create()
    {
        return view('operations.form', $this->donneesFormulaire(new OperationFinanciere([
            'type' => 'recette', 'date_operation' => now(), 'mode_paiement' => 'Espèces',
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->valider($request);

        // L'opération appartient au cycle du comptable ; la Direction choisit (vide = commun)
        $data['cycle_id'] = PorteeCycle::pourUtilisateurConnecte() ?: ($data['cycle_id'] ?? null);
        $data['saisi_par'] = auth()->id();
        $data['annee_scolaire'] = ConfigurationEcole::anneeActive();

        if ($request->hasFile('piece_justificative')) {
            $data['piece_justificative'] = Fichier::enregistrer($request->file('piece_justificative'), 'pieces_justificatives', 'piece_');
        }

        OperationFinanciere::create($data);

        return redirect()->route('operations.index')->with('success', 'Opération enregistrée.');
    }

    public function edit(OperationFinanciere $operation)
    {
        $this->verifierModifiable($operation);

        return view('operations.form', $this->donneesFormulaire($operation));
    }

    // Correction d'une opération : le motif et les anciennes valeurs sont gardés au journal
    public function update(Request $request, OperationFinanciere $operation)
    {
        $this->verifierModifiable($operation);
        $data = $this->valider($request, true);
        $data['cycle_id'] = PorteeCycle::pourUtilisateurConnecte() ?: ($data['cycle_id'] ?? null);
        $motif = $data['motif'];
        unset($data['motif']);

        $avant = $this->instantane($operation);
        if ($request->hasFile('piece_justificative')) {
            $data['piece_justificative'] = Fichier::enregistrer($request->file('piece_justificative'), 'pieces_justificatives', 'piece_');
        } else {
            unset($data['piece_justificative']);
        }
        $operation->update($data);

        [$a, $b] = JournalFinancier::differences($avant, $this->instantane($operation->fresh()));
        if ($a || $b) {
            JournalFinancier::noter('modification', 'operation', $operation, $motif, $a, $b, $this->reference($operation), $operation->cycle_id);
        }

        return redirect()->route('operations.index')->with('success', 'Opération corrigée.');
    }

    public function destroy(Request $request, OperationFinanciere $operation)
    {
        $this->verifierModifiable($operation);
        $motif = $request->validate(['motif' => 'required|string|max:255'])['motif'];

        JournalFinancier::noter('suppression', 'operation', $operation, $motif, $this->instantane($operation), null, $this->reference($operation), $operation->cycle_id);
        $operation->delete();

        return redirect()->route('operations.index')->with('success', 'Opération supprimée.');
    }

    private function verifierModifiable(OperationFinanciere $operation): void
    {
        PorteeCycle::verifierCycle($operation->cycle_id);
        abort_if($operation->paie_id, 403, 'Ce salaire vient de la Paie du personnel : corrigez ou supprimez le bulletin de paie.');
    }

    private function valider(Request $request, bool $correction = false): array
    {
        $data = $request->validate([
            'type' => 'required|in:recette,depense',
            'categorie' => 'required|string|max:100',
            'montant' => 'required|numeric|min:1',
            'mode_paiement' => 'nullable|in:' . implode(',', OperationFinanciere::MODES),
            'date_operation' => 'required|date',
            'description' => 'nullable|string|max:255',
            'piece_justificative' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'cycle_id' => 'nullable|integer|exists:cycles,id',
        ] + ($correction ? ['motif' => 'required|string|max:255'] : []));
        $data['mode_paiement'] ??= 'Espèces';

        return $data;
    }

    private function donneesFormulaire(OperationFinanciere $operation): array
    {
        return [
            'operation' => $operation,
            'categoriesRecette' => OperationFinanciere::categoriesRecette(),
            'categoriesDepense' => OperationFinanciere::categoriesDepense(),
            'modesPaiement' => OperationFinanciere::MODES,
            'cycles' => PorteeCycle::pourUtilisateurConnecte() ? collect() : Cycle::orderBy('nom')->get(),
        ];
    }

    private function instantane(OperationFinanciere $operation): array
    {
        return collect($operation->only(self::CHAMPS_JOURNAL))
            ->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v)->all();
    }

    private function reference(OperationFinanciere $operation): string
    {
        return ($operation->type === 'recette' ? 'Recette' : 'Dépense') . ' « ' . $operation->categorie . ' » du ' . $operation->date_operation->format('d/m/Y');
    }
}

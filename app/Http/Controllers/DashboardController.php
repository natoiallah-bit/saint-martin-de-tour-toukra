<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use App\Models\ClasseScolaire;
use App\Models\Personnel;
use App\Models\Paiement;
use App\Models\OperationFinanciere;
use App\Models\FraisScolarite;
use App\Helpers\PorteeCycle;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Le Principal a son propre tableau de bord : un accueil qui
        // regroupe tout ce qui est pédagogique, sans aucune donnée
        // financière/administrative (voir dashboard-principal.blade.php).
        if ($user && $user->role === 'principal') {
            $cycleId = PorteeCycle::pourUtilisateurConnecte();

            $totalClasses = ClasseScolaire::when($cycleId, fn($q) => $q->where('cycle_id', $cycleId))->count();
            $totalEleves = Eleve::when($cycleId, fn($q) => $q->whereHas('classe', fn($qq) => $qq->where('cycle_id', $cycleId)))->count();
            $totalEnseignants = Personnel::where('type', 'enseignant')->where('actif', true)->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->count();

            return view('dashboard-principal', compact('totalClasses', 'totalEleves', 'totalEnseignants'));
        }

        // Si l'utilisateur n'a pas le droit de voir le tableau de bord global
        // (direction / secrétariat uniquement), on le redirige gentiment
        // vers une page utile selon son rôle, au lieu d'une erreur 403.
        if (!$user || !$user->peutVoirDashboard()) {
            $page = $user?->pageAccueil();
            abort_if(! $page && $user?->aRole('comptable') && ! $user->cycle_id, 403, "Votre compte comptable n'est rattaché à aucun cycle. Demandez à la Direction de choisir votre cycle dans « Utilisateurs ».");
            abort_if(! $page || $page === 'dashboard', 403, "Votre compte n'a accès à aucune page. Demandez à la Direction de vérifier vos droits.");

            return redirect()->route($page);
        }

        $anneeScolaire = ConfigurationEcole::anneeActive();
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $totalEleves = Eleve::when($cycleId, fn($q) => $q->whereHas('classe', fn($qq) => $qq->where('cycle_id', $cycleId)))->count();
        $totalClasses = ClasseScolaire::when($cycleId, fn($q) => $q->where('cycle_id', $cycleId))->count();
        $totalEnseignants = Personnel::where('type', 'enseignant')->where('actif', true)->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->count();

        $totalScolarite = Paiement::where('annee_scolaire', $anneeScolaire)
            ->when($cycleId, fn($q) => $q->whereHas('eleve.classe', fn($qq) => $qq->where('cycle_id', $cycleId)))
            ->sum('montant');

        // Recettes et dépenses de la même année scolaire (et du même cycle) que la scolarité
        $operations = OperationFinanciere::where('annee_scolaire', $anneeScolaire)
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId));
        $totalDepenses = (clone $operations)->where('type', 'depense')->sum('montant');
        $totalRecettesManuelles = (clone $operations)->where('type', 'recette')->sum('montant');
        $solde = ($totalScolarite + $totalRecettesManuelles) - $totalDepenses;

        $totalAttendu = 0;
        $cyclesAConsiderer = $cycleId ? \App\Models\Cycle::where('id', $cycleId)->get() : \App\Models\Cycle::all();
        foreach ($cyclesAConsiderer as $cycle) {
            // Les élèves exonérés ne paient rien : ils ne comptent pas dans ce qui est attendu
            $nbElevesCycle = Eleve::where('exonere', false)->whereHas('classe', fn($q) => $q->where('cycle_id', $cycle->id))->count();
            $frais = FraisScolarite::where('cycle_id', $cycle->id)->where('annee_scolaire', $anneeScolaire)->first();
            $totalAttendu += $nbElevesCycle * ($frais?->montant_annuel ?? 0);
        }
        $tauxRecouvrement = $totalAttendu > 0 ? round(($totalScolarite / $totalAttendu) * 100) : 0;

        $effectifsParClasse = ClasseScolaire::withCount('eleves')
            ->with('cycle')
            ->when($cycleId, fn($q) => $q->where('cycle_id', $cycleId))
            ->ordonnees()
            ->get();

        return view('dashboard', compact(
            'totalEleves', 'totalClasses', 'totalEnseignants',
            'totalScolarite', 'totalDepenses', 'solde', 'tauxRecouvrement',
            'effectifsParClasse'
        ));
    }
}

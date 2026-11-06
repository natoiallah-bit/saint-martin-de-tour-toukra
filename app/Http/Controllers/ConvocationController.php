<?php

namespace App\Http\Controllers;

use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use App\Models\FraisScolarite;
use App\Models\RelancePaiement;
use App\Models\User;
use App\Helpers\PorteeCycle;
use App\Helpers\SuiviPaiements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConvocationController extends Controller
{
    // ---------- RETARDS DE PAIEMENT ----------

    // Familles en retard : ce qui devait être payé à ce jour (échéancier du cycle) moins ce qui a été versé
    public function retardsIndex(Request $request)
    {
        $annee = ConfigurationEcole::anneeActive();
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $classes = ClasseScolaire::with('cycle')->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->ordonnees()->get();
        $classeId = $request->integer('classe_id') ?: null;

        // Toute la famille compte (un frère dans une autre classe aussi), le filtre s'applique ensuite
        $situations = SuiviPaiements::situations($this->elevesVisibles()->get(), $annee);
        $familles = SuiviPaiements::famillesEnRetard($situations)
            ->when($classeId, fn ($f) => $f->filter(fn ($famille) => $famille['enfants']->contains(fn ($s) => (int) $s['eleve']->classe_id === $classeId)))
            ->when($request->filled('niveau'), fn ($f) => $f->where('niveau_suivant', (int) $request->niveau))
            ->values();

        return view('convocations.retards', [
            'familles' => $familles,
            'annee' => $annee,
            'classes' => $classes,
            'echeancierConfigure' => FraisScolarite::where('annee_scolaire', $annee)->whereNotNull('montant_tranche1')
                ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->exists(),
        ]);
    }

    // Lettres de relance, une par famille. "Aperçu" n'enregistre rien ; sinon chaque lettre est
    // inscrite dans l'historique et la prochaine lettre de la famille sera du niveau suivant.
    public function retardsGenerer(Request $request)
    {
        $request->validate([
            'familles' => 'required|array|min:1',
            'familles.*' => ['string', 'regex:/^\d+(-\d+)*$/'],
            'action' => 'nullable|in:apercu,enregistrer',
            'delai' => 'nullable|integer|min:1|max:60',
        ], ['familles.required' => 'Cochez au moins une famille.']);

        $config = ConfigurationEcole::actuelle();
        $annee = $config->annee_scolaire_active;
        $enregistrer = $request->input('action') === 'enregistrer';
        $dateLimite = now()->addDays((int) $request->input('delai', 8))->startOfDay();

        $groupes = collect($request->familles)->map(fn ($cle) => array_map('intval', explode('-', $cle)));
        $eleves = $this->elevesVisibles()->whereIn('id', $groupes->flatten())->get();
        $situations = SuiviPaiements::situations($eleves, $annee);

        $lettres = $groupes
            ->map(fn ($ids) => SuiviPaiements::famille($situations->only($ids)->filter(fn ($s) => $s['retard'] > 0)))
            ->filter()   // sécurité : uniquement les retards réels
            ->values();

        if ($lettres->isEmpty()) {
            return back()->with('error', "Aucune des familles sélectionnées n'a de retard de paiement : aucune lettre générée.");
        }

        if ($enregistrer) {
            DB::transaction(function () use ($lettres, $annee, $dateLimite) {
                foreach ($lettres as $famille) {
                    foreach ($famille['enfants'] as $s) {
                        RelancePaiement::create([
                            'eleve_id' => $s['eleve']->id,
                            'annee_scolaire' => $annee,
                            'niveau' => $famille['niveau_suivant'],
                            'montant_du' => $s['retard'],
                            'date_relance' => now()->toDateString(),
                            'date_limite' => $dateLimite->toDateString(),
                            'envoyee_par' => auth()->id(),
                        ]);
                    }
                }
            });
        }

        return view('convocations.lettres-retard', [
            'lettres' => $lettres,
            'config' => $config,
            'dateLimite' => $dateLimite,
            'enregistre' => $enregistrer,
        ]);
    }

    // Historique des relances envoyées cette année
    public function retardsHistorique()
    {
        $annee = ConfigurationEcole::anneeActive();
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $relances = RelancePaiement::with(['eleve.classe'])
            ->where('annee_scolaire', $annee)
            ->when($cycleId, fn ($q) => $q->whereHas('eleve.classe', fn ($c) => $c->where('cycle_id', $cycleId)))
            ->orderByDesc('date_relance')->orderByDesc('id')
            ->paginate(40);

        $auteurs = User::whereIn('id', $relances->pluck('envoyee_par')->filter()->unique())->pluck('name', 'id');

        return view('convocations.historique', compact('relances', 'annee', 'auteurs'));
    }

    private function elevesVisibles()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return Eleve::with('classe.cycle')
            ->when($cycleId, fn ($q) => $q->whereHas('classe', fn ($c) => $c->where('cycle_id', $cycleId)))
            ->orderBy('nom')->orderBy('prenom');
    }

    // ---------- CONVOCATIONS RÉUNION / AUTRE ----------

    public function reunionForm()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $classes = ClasseScolaire::with('cycle')->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->ordonnees()->get();
        return view('convocations.reunion-form', compact('classes'));
    }

    public function reunionGenerer(Request $request)
    {
        $data = $request->validate([
            'cible' => 'required|in:toute_ecole,classe',
            'classe_id' => ['required_if:cible,classe', 'nullable', PorteeCycle::regleClasse()],
            'sujet' => 'required|string|max:200',
            'date_convocation' => 'required|date',
            'heure' => 'required',
            'lieu' => 'required|string|max:150',
            'message' => 'nullable|string',
        ]);

        $query = Eleve::with('classe.cycle')
            ->join('classes', 'eleves.classe_id', '=', 'classes.id')
            ->select('eleves.*')
            ->orderBy('classes.ordre')->orderBy('classes.nom')->orderBy('eleves.nom')->orderBy('eleves.prenom');
        if ($data['cible'] === 'classe') {
            $query->where('eleves.classe_id', $data['classe_id']);
        }
        // « Toute l'école » : seulement le cycle de l'utilisateur s'il est limité à un cycle
        if ($cycleId = PorteeCycle::pourUtilisateurConnecte()) {
            $query->where('classes.cycle_id', $cycleId);
        }
        $eleves = $query->get();

        $config = ConfigurationEcole::actuelle();

        return view('convocations.lettres-reunion', [
            'eleves' => $eleves,
            'config' => $config,
            'sujet' => $data['sujet'],
            'date_convocation' => $data['date_convocation'],
            'heure' => $data['heure'],
            'lieu' => $data['lieu'],
            'message' => $data['message'] ?? null,
        ]);
    }
}
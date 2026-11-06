<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\DossierDiscipline;
use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Conseil de discipline : l'élève est convoqué (motif, date du conseil), puis la décision
// est enregistrée. Le parent est alerté dans son espace à chaque étape.
class DisciplineController extends Controller
{
    public function index(Request $request)
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $dossiers = DossierDiscipline::with('eleve.classe')
            ->whereHas('eleve.classe', fn ($q) => $q->when($cycleId, fn ($c) => $c->where('cycle_id', $cycleId)))
            ->when($request->statut === 'attente', fn ($q) => $q->whereNull('decision'))
            ->when($request->statut === 'decide', fn ($q) => $q->whereNotNull('decision'))
            ->when($request->filled('classe_id'), fn ($q) => $q->whereHas('eleve', fn ($e) => $e->where('classe_id', $request->integer('classe_id'))))
            ->orderByRaw('decision is not null')->orderByDesc('date_conseil')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $classes = ClasseScolaire::when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->ordonnees()->get();

        return view('discipline.index', compact('dossiers', 'classes'));
    }

    public function create(Request $request)
    {
        return view('discipline.form', [
            'dossier' => new DossierDiscipline(['eleve_id' => $request->integer('eleve_id') ?: null, 'date_faits' => now()]),
            'eleves' => $this->elevesVisibles(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $dossier = DossierDiscipline::create($donnees + ['cree_par' => auth()->id()]);

        return redirect()->route('discipline.show', $dossier)
            ->with('success', 'Comparution enregistrée : le parent est alerté dans son espace. Vous pouvez aussi imprimer la convocation.');
    }

    public function show(DossierDiscipline $dossier)
    {
        $this->autoriser($dossier);
        $dossier->load(['eleve.classe', 'createur', 'decideur']);
        $historique = DossierDiscipline::where('eleve_id', $dossier->eleve_id)->whereKeyNot($dossier->id)->orderByDesc('date_conseil')->get();

        return view('discipline.show', compact('dossier', 'historique'));
    }

    public function edit(DossierDiscipline $dossier)
    {
        $this->autoriser($dossier);

        return view('discipline.form', ['dossier' => $dossier, 'eleves' => $this->elevesVisibles()]);
    }

    public function update(Request $request, DossierDiscipline $dossier)
    {
        $this->autoriser($dossier);
        $donnees = $this->valider($request);
        // Date ou motif changés : le parent doit relire la convocation
        $nouvelleDate = ! empty($donnees['date_conseil']) ? \Carbon\Carbon::parse($donnees['date_conseil'])->format('Y-m-d H:i') : null;
        if ($dossier->motif !== $donnees['motif'] || $dossier->date_conseil?->format('Y-m-d H:i') !== $nouvelleDate) {
            $donnees['convocation_lue_le'] = null;
        }
        $dossier->update($donnees);

        return redirect()->route('discipline.show', $dossier)->with('success', 'Comparution modifiée.');
    }

    public function destroy(DossierDiscipline $dossier)
    {
        $this->autoriser($dossier);
        $dossier->delete();

        return redirect()->route('discipline.index')->with('success', 'Dossier supprimé.');
    }

    // Décision du conseil ; le parent est de nouveau alerté
    public function decider(Request $request, DossierDiscipline $dossier)
    {
        $this->autoriser($dossier);
        $donnees = $request->validate([
            'decision' => ['required', Rule::in(array_keys(DossierDiscipline::DECISIONS))],
            'decision_details' => 'nullable|string|max:3000',
            'date_decision' => 'required|date',
        ]);
        $dossier->update($donnees + ['decide_par' => auth()->id(), 'decision_lue_le' => null]);

        return back()->with('success', 'Décision enregistrée : ' . $dossier->libelle_decision . '. Le parent est alerté.');
    }

    // Lettre de convocation à remettre au parent (pour ceux qui n'ont pas de compte)
    public function convocation(DossierDiscipline $dossier)
    {
        $this->autoriser($dossier);

        return view('discipline.convocation', ['dossier' => $dossier->load('eleve.classe'), 'config' => ConfigurationEcole::actuelle()]);
    }

    // Le parent confirme avoir pris connaissance (convocation, puis décision)
    public function lu(DossierDiscipline $dossier)
    {
        $user = auth()->user();
        abort_unless($user->estParentDe($dossier->eleve_id), 403);
        $dossier->update($dossier->estDecide() && $dossier->convocation_lue_le
            ? ['decision_lue_le' => now()]
            : ['convocation_lue_le' => now(), 'decision_lue_le' => $dossier->estDecide() ? now() : null]);

        return back()->with('success', 'Merci : la direction sait que vous avez pris connaissance de ce message.');
    }

    protected function valider(Request $request): array
    {
        $donnees = $request->validate([
            'eleve_id' => 'required|integer|exists:eleves,id',
            'motif' => 'required|string|max:200',
            'faits' => 'nullable|string|max:5000',
            'date_faits' => 'nullable|date|before_or_equal:today',
            'date_conseil' => 'nullable|date',
            'lieu' => 'nullable|string|max:150',
        ]);
        PorteeCycle::verifierEleve(Eleve::findOrFail($donnees['eleve_id']));

        return $donnees;
    }

    protected function autoriser(DossierDiscipline $dossier): void
    {
        PorteeCycle::verifierEleve($dossier->eleve);
    }

    protected function elevesVisibles()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return Eleve::with('classe')
            ->join('classes', 'eleves.classe_id', '=', 'classes.id')
            ->when($cycleId, fn ($q) => $q->where('classes.cycle_id', $cycleId))
            ->select('eleves.*')
            ->orderBy('classes.ordre')->orderBy('classes.nom')->orderBy('eleves.nom')->orderBy('eleves.prenom')
            ->get();
    }
}

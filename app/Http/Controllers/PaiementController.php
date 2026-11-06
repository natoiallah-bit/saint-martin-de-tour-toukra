<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationEcole;
use App\Helpers\Fichier;

use App\Helpers\PorteeCycle;
use App\Models\Eleve;
use App\Models\FraisScolarite;
use App\Models\JournalFinancier;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    // Année scolaire en cours, réglée dans Paramètres
    protected function anneeCourante(): string
    {
        return ConfigurationEcole::anneeActive();
    }

    public function index(Request $request)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $query = Paiement::with('eleve.classe.cycle')->orderByDesc('date_paiement');

        if ($cycleId) {
            $query->whereHas('eleve.classe', fn ($q) => $q->where('cycle_id', $cycleId));
        }

        if ($request->filled('eleve_id')) {
            $query->where('eleve_id', $request->eleve_id);
        }

        // Par défaut, les paiements de l'année scolaire active (les autres années restent consultables)
        $annees = Paiement::query()->distinct()->orderByDesc('annee_scolaire')->pluck('annee_scolaire');
        $annee = $request->input('annee', $this->anneeCourante());
        if ($annee !== 'toutes') {
            $query->where('annee_scolaire', $annee);
        }

        $paiements = $query->paginate(20)->withQueryString();

        $eleves = Eleve::when($cycleId, fn ($q) => $q->whereHas('classe', fn ($qq) => $qq->where('cycle_id', $cycleId)))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $totalEncaisse = (clone $query)->sum('montant');

        return view('paiements.index', compact('paiements', 'eleves', 'totalEncaisse', 'annees', 'annee'));
    }

    public function create()
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $eleves = Eleve::with('classe.cycle')
            ->when($cycleId, fn ($q) => $q->whereHas('classe', fn ($qq) => $qq->where('cycle_id', $cycleId)))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $typesDisponibles = Paiement::libellesDisponibles();

        return view('paiements.create', [
            'eleves' => $eleves,
            'typesDisponibles' => $typesDisponibles,
            'anneeCourante' => $this->anneeCourante(),
        ]);
    }

    /**
     * Endpoint AJAX : renvoie le statut financier d'un élève
     * (montant annuel, déjà versé, restant à payer)
     */
    public function statutEleve(Eleve $eleve)
    {
        if (auth()->user()->role === 'parent' && ! auth()->user()->estParentDe($eleve->id)) {
            abort(403);
        }
        if (auth()->user()->role !== 'parent') {
            PorteeCycle::verifierEleve($eleve);
        }

        $cycleId = $eleve->classe->cycle_id ?? null;

        $frais = $cycleId ? FraisScolarite::pourCycle($cycleId, $this->anneeCourante()) : null;
        $montantAnnuel = $frais ? (float) $frais->montant_annuel : 0.0;

        $verse = Paiement::totalVerse($eleve->id, $this->anneeCourante());

        $exonere = (bool) ($eleve->exonere ?? false);

        return response()->json([
            'montant_annuel' => $montantAnnuel,
            'verse' => $verse,
            'restant' => $exonere ? 0 : max($montantAnnuel - $verse, 0),
            // Retard à ce jour selon l'échéancier du cycle
            'en_retard' => $exonere || ! $frais ? 0 : max($frais->montantExigible() - $verse, 0),
            'exonere' => $exonere,
        ]);
    }

    public function store(Request $request)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        $validated = $request->validate([
            'eleve_id' => ['required', 'exists:eleves,id'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string'],
            'montant' => ['required', 'numeric', 'min:1'],
            'mode_paiement' => ['required', 'in:especes,cheque,virement,mobile_money'],
            'date_paiement' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'piece_justificative' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Un comptable n'encaisse que pour les élèves de son cycle
        PorteeCycle::verifierEleve(Eleve::findOrFail($validated['eleve_id']));

        $cheminFichier = null;

        if ($request->hasFile('piece_justificative')) {
            $cheminFichier = Fichier::enregistrer($request->file('piece_justificative'), 'pieces_justificatives', 'justificatif_');
        }

        $paiement = DB::transaction(function () use ($validated, $cheminFichier) {
            return Paiement::create([
                'eleve_id' => $validated['eleve_id'],
                'annee_scolaire' => $this->anneeCourante(),
                'types' => $validated['types'],
                'montant' => $validated['montant'],
                'mode_paiement' => $validated['mode_paiement'],
                'date_paiement' => $validated['date_paiement'],
                'numero_recu' => Paiement::genererNumeroRecu($this->anneeCourante()),
                'description' => $validated['description'] ?? null,
                'piece_justificative' => $cheminFichier,
                'saisi_par' => auth()->id(),
            ]);
        });

        return redirect()->route('paiements.recu', $paiement)
            ->with('success', 'Paiement enregistré avec succès.');
    }

    public function recu(Paiement $paiement)
    {
        $paiement->load('eleve.classe.cycle');

        if (auth()->user()->role === 'parent' && ! auth()->user()->estParentDe($paiement->eleve_id)) {
            abort(403, "Ce reçu ne concerne pas l'un de vos enfants.");
        }
        if (auth()->user()->role !== 'parent') {
            PorteeCycle::verifierEleve($paiement->eleve);
        }

        $cycleId = $paiement->eleve->classe->cycle_id ?? null;

        $montantAnnuel = $cycleId
            ? FraisScolarite::montantPourCycle($cycleId, $paiement->annee_scolaire)
            : 0.0;

        $verseTotal = Paiement::totalVerse($paiement->eleve_id, $paiement->annee_scolaire);
        // Élève exonéré : il n'a rien à payer
        $restant = $paiement->eleve->exonere ? 0.0 : max($montantAnnuel - $verseTotal, 0);

        $montantEnLettres = \App\Helpers\NombreEnLettres::montantFcfa($paiement->montant);

        // Le parent ne reçoit que son exemplaire ; la caisse imprime les deux sur une page
        $exemplaires = auth()->user()->role === 'parent' ? ['Parent'] : ['Caisse', 'Parent'];
        // Le reçu est signé par le ou la comptable qui a encaissé
        $nomComptable = $paiement->encaissePar?->name;
        // Reçu corrigé après son émission : la date de la dernière correction est imprimée
        $corrigeLe = JournalFinancier::where('objet', 'paiement')->where('objet_id', $paiement->id)->where('action', 'modification')->latest()->value('created_at');

        return view('paiements.recu', compact('paiement', 'montantAnnuel', 'verseTotal', 'restant', 'montantEnLettres', 'exemplaires', 'nomComptable', 'corrigeLe'));
    }

    // Correction d'un paiement : le numéro de reçu reste le même, le motif et les
    // anciennes valeurs sont gardés au journal consulté par la Direction
    public function edit(Paiement $paiement)
    {
        $paiement->load('eleve.classe');
        PorteeCycle::verifierEleve($paiement->eleve);

        return view('paiements.edit', ['paiement' => $paiement, 'typesDisponibles' => Paiement::libellesDisponibles()]);
    }

    public function update(Request $request, Paiement $paiement)
    {
        $paiement->load('eleve.classe');
        PorteeCycle::verifierEleve($paiement->eleve);

        $data = $request->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string'],
            'montant' => ['required', 'numeric', 'min:1'],
            'mode_paiement' => ['required', 'in:especes,cheque,virement,mobile_money'],
            'date_paiement' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'motif' => ['required', 'string', 'max:255'],
        ]);
        $motif = $data['motif'];
        unset($data['motif']);

        $avant = $this->instantane($paiement);
        $paiement->update($data);
        [$a, $b] = JournalFinancier::differences($avant, $this->instantane($paiement->fresh()));
        if ($a || $b) {
            JournalFinancier::noter('modification', 'paiement', $paiement, $motif, $a, $b,
                'Reçu ' . $paiement->numero_recu . ' — ' . $paiement->eleve->nom . ' ' . $paiement->eleve->prenom, $paiement->eleve->classe?->cycle_id);
        }

        return redirect()->route('paiements.index')->with('success', 'Paiement ' . $paiement->numero_recu . ' corrigé.');
    }

    public function destroy(Request $request, Paiement $paiement)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }
        $paiement->load('eleve.classe');
        PorteeCycle::verifierEleve($paiement->eleve);
        $motif = $request->validate(['motif' => 'required|string|max:255'])['motif'];

        JournalFinancier::noter('suppression', 'paiement', $paiement, $motif, $this->instantane($paiement), null,
            'Reçu ' . $paiement->numero_recu . ' — ' . ($paiement->eleve->nom ?? '') . ' ' . ($paiement->eleve->prenom ?? ''), $paiement->eleve?->classe?->cycle_id);
        $paiement->delete();

        return redirect()->route('paiements.index')->with('success', 'Paiement supprimé.');
    }

    private function instantane(Paiement $paiement): array
    {
        return [
            'montant' => (float) $paiement->montant,
            'mode_paiement' => $paiement->libelle_mode_paiement,
            'date_paiement' => \Carbon\Carbon::parse($paiement->date_paiement)->format('Y-m-d'),
            'types' => $paiement->libelle_types,
            'description' => $paiement->description,
        ];
    }
}

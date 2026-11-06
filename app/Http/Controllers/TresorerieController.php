<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Helpers\Tresorerie;
use App\Models\ConfigurationEcole;
use App\Models\Cycle;
use App\Models\JournalFinancier;
use App\Models\MouvementTresorerie;
use App\Models\SoldeOuverture;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Caisse, banque et Mobile Money : soldes, journal du jour, versements entre comptes
class TresorerieController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['jour' => 'nullable|date']);
        $annee = ConfigurationEcole::anneeActive();
        $portee = $this->portee($request);
        $jour = $request->filled('jour') ? Carbon::parse($request->jour) : now();

        return view('tresorerie.index', [
            'annee' => $annee,
            'portee' => $portee,
            'jour' => $jour,
            'situation' => Tresorerie::situation($annee, $portee, $jour),
            'aujourdhui' => Tresorerie::situation($annee, $portee, now()),
            'ouverture' => Tresorerie::soldesOuverture($annee, $portee),
            'versements' => MouvementTresorerie::with(['cycle', 'auteur'])->where('annee_scolaire', $annee)
                ->where(fn ($q) => Tresorerie::filtrerPortee($q, $portee))
                ->orderByDesc('date_mouvement')->orderByDesc('id')->take(15)->get(),
            'cycles' => PorteeCycle::pourUtilisateurConnecte() ? collect() : Cycle::orderBy('nom')->get(),
            'cycleImpose' => ($id = PorteeCycle::pourUtilisateurConnecte()) ? Cycle::find($id) : null,
            // Saisie possible seulement pour un cycle précis ou le commun (pas « toute l'école »)
            'saisiePossible' => $portee !== null,
        ]);
    }

    // Soldes de départ de l'année (argent en caisse, en banque et sur le Mobile Money à la rentrée)
    public function soldes(Request $request)
    {
        $portee = $this->portee($request);
        abort_if($portee === null, 422, 'Choisissez un cycle ou le commun.');
        $data = $request->validate(['soldes' => 'required|array', 'soldes.*' => 'nullable|numeric|min:0']);
        $annee = ConfigurationEcole::anneeActive();
        $cycleId = $portee === 'commun' ? null : (int) $portee;

        foreach (array_keys(Tresorerie::COMPTES) as $compte) {
            $montant = (float) ($data['soldes'][$compte] ?? 0);
            $solde = SoldeOuverture::where('annee_scolaire', $annee)->where('compte', $compte)
                ->where(fn ($q) => Tresorerie::filtrerPortee($q, $portee))->first();
            $ancien = $solde ? (float) $solde->montant : null;
            if ($ancien === $montant) {
                continue;
            }
            $solde ??= new SoldeOuverture(['annee_scolaire' => $annee, 'compte' => $compte, 'cycle_id' => $cycleId]);
            $solde->montant = $montant;
            $solde->save();

            // Un solde de départ déjà saisi qui change : gardé au journal
            if ($ancien !== null) {
                JournalFinancier::noter('modification', 'solde', $solde, 'Solde de départ corrigé',
                    ['montant' => $ancien], ['montant' => $montant], Tresorerie::COMPTES[$compte] . ' — ' . $annee, $cycleId);
            }
        }

        return back()->with('success', 'Soldes de départ enregistrés.');
    }

    // Versement entre deux comptes (ex. dépôt des espèces de la caisse à la banque)
    public function versement(Request $request)
    {
        $portee = $this->portee($request);
        abort_if($portee === null, 422, 'Choisissez un cycle ou le commun.');
        $comptes = implode(',', array_keys(Tresorerie::COMPTES));
        $data = $request->validate([
            'compte_source' => 'required|in:' . $comptes,
            'compte_destination' => 'required|different:compte_source|in:' . $comptes,
            'montant' => 'required|numeric|min:1',
            'date_mouvement' => 'required|date',
            'description' => 'nullable|string|max:255',
        ], ['compte_destination.different' => 'Le versement doit aller vers un autre compte.']);

        MouvementTresorerie::create($data + [
            'annee_scolaire' => ConfigurationEcole::anneeActive(),
            'cycle_id' => $portee === 'commun' ? null : (int) $portee,
            'saisi_par' => auth()->id(),
        ]);

        return back()->with('success', 'Versement enregistré.');
    }

    public function supprimerVersement(Request $request, MouvementTresorerie $mouvement)
    {
        PorteeCycle::verifierCycle($mouvement->cycle_id);
        $motif = $request->validate(['motif' => 'required|string|max:255'])['motif'];

        JournalFinancier::noter('suppression', 'mouvement', $mouvement, $motif, [
            'compte_source' => Tresorerie::COMPTES[$mouvement->compte_source],
            'compte_destination' => Tresorerie::COMPTES[$mouvement->compte_destination],
            'montant' => (float) $mouvement->montant,
            'date_mouvement' => $mouvement->date_mouvement->format('Y-m-d'),
        ], null, 'Versement du ' . $mouvement->date_mouvement->format('d/m/Y'), $mouvement->cycle_id);
        $mouvement->delete();

        return back()->with('success', 'Versement supprimé.');
    }

    // Comptable : son cycle ; Direction : un cycle, le commun, ou toute l'école (null)
    private function portee(Request $request): int|string|null
    {
        if ($cycleId = PorteeCycle::pourUtilisateurConnecte()) {
            return $cycleId;
        }
        $choix = $request->input('cycle');

        return $choix === 'commun' ? 'commun' : ($choix ? (int) $choix : null);
    }
}

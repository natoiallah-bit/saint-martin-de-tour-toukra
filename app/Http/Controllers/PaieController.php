<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Models\JournalFinancier;
use App\Models\Paie;
use App\Models\Personnel;
use Illuminate\Http\Request;

class PaieController extends Controller
{
    // Liste des bulletins générés
    public function index(Request $request)
    {
        $mois = $request->get('mois', now()->format('Y-m'));
        $paies = Paie::with('personnel')->where('mois', $mois)
            ->whereHas('personnel', fn ($q) => $this->portee($q))->get();
        $personnelsSansPaie = Personnel::where('actif', true)->where(fn ($q) => $this->portee($q))
            ->whereNotIn('id', $paies->pluck('personnel_id'))
            ->get();

        return view('paies.index', compact('paies', 'mois', 'personnelsSansPaie'));
    }

    // Formulaire de création/édition du bulletin d'un personnel pour un mois
    public function create(Request $request)
    {
        $personnels = Personnel::where('actif', true)->where(fn ($q) => $this->portee($q))->orderBy('nom')->get();
        $mois = $request->get('mois', now()->format('Y-m'));
        return view('paies.create', compact('personnels', 'mois'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'personnel_id' => 'required|exists:personnels,id',
            'mois' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'date_paiement' => 'required|date',
            'mode_paiement' => 'required|in:' . implode(',', Paie::MODES),
            'nombre_heures' => 'nullable|numeric|min:0|max:744',
            'salaire_base' => 'nullable|numeric|min:0|max:99999999',
            'primes' => 'nullable|numeric|min:0|max:99999999',
            'acompte' => 'nullable|numeric|min:0|max:99999999',
            'retenues' => 'nullable|numeric|min:0|max:99999999',
        ]);

        $personnel = Personnel::findOrFail($data['personnel_id']);
        PorteeCycle::verifierCycle($personnel->cycle_id);

        if ($personnel->paiement_horaire) {
            $nombreHeures = (float) ($data['nombre_heures'] ?? 0);
            $tauxHoraire = (float) ($personnel->taux_horaire ?? 0);

            $salaireBase = round($nombreHeures * $tauxHoraire, 2);
            $nombreHeuresEnregistre = $nombreHeures;
            $tauxHoraireEnregistre = $tauxHoraire;
        } else {
            $salaireBase = (float) ($data['salaire_base'] ?? 0);
            $nombreHeuresEnregistre = null;
            $tauxHoraireEnregistre = null;
        }

        $existante = Paie::where('personnel_id', $data['personnel_id'])->where('mois', $data['mois'])->first();
        $avant = $existante?->only(['salaire_base', 'nombre_heures', 'primes', 'acompte', 'retenues', 'date_paiement', 'mode_paiement']);

        $paie = Paie::updateOrCreate(
            ['personnel_id' => $data['personnel_id'], 'mois' => $data['mois']],
            [
                'date_paiement' => $data['date_paiement'],
                'mode_paiement' => $data['mode_paiement'],
                'salaire_base' => $salaireBase,
                'nombre_heures' => $nombreHeuresEnregistre,
                'taux_horaire' => $tauxHoraireEnregistre,
                'primes' => $data['primes'] ?? 0,
                'acompte' => $data['acompte'] ?? 0,
                'retenues' => $data['retenues'] ?? 0,
            ]
        );

        // Le salaire devient une dépense « Salaires » en comptabilité (et sort de la caisse ou de la banque)
        $paie->synchroniserDepense();

        // Bulletin refait : on garde la trace de l'ancien montant
        if ($avant) {
            [$a, $b] = JournalFinancier::differences(
                array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $avant),
                array_merge($paie->only(array_keys($avant)), ['date_paiement' => $paie->date_paiement?->format('Y-m-d')])
            );
            if ($a || $b) {
                JournalFinancier::noter('modification', 'paie', $paie, 'Bulletin de paie refait', $a, $b,
                    $paie->mois_libelle . ' — ' . $personnel->nom . ' ' . $personnel->prenom, $personnel->cycle_id);
            }
        }

        return redirect()->route('paies.bulletin', $paie)->with('success', 'Bulletin de paie enregistré ; le salaire est ajouté aux dépenses.');
    }

    // Affiche le bulletin de paie imprimable
    public function bulletin(Paie $paie)
    {
        $paie->load('personnel');
        PorteeCycle::verifierCycle($paie->personnel?->cycle_id);
        return view('paies.bulletin', compact('paie'));
    }

    public function destroy(Request $request, Paie $paie)
    {
        PorteeCycle::verifierCycle($paie->personnel?->cycle_id);
        $motif = $request->validate(['motif' => 'required|string|max:255'])['motif'];
        $mois = $paie->mois;
        $paie->load('personnel');
        JournalFinancier::noter('suppression', 'paie', $paie, $motif,
            $paie->only(['salaire_base', 'primes', 'acompte', 'retenues']) + ['net_a_payer' => $paie->net_a_payer], null,
            $paie->mois_libelle . ' — ' . ($paie->personnel->nom ?? '') . ' ' . ($paie->personnel->prenom ?? ''), $paie->personnel?->cycle_id);
        $paie->depense()->delete();
        $paie->delete();
        return redirect()->route('paies.index', ['mois' => $mois])->with('success', 'Bulletin supprimé.');
    }

    // Un comptable ne gère que la paie du personnel de son cycle ;
    // le personnel commun (sans cycle) est géré par la Direction
    private function portee($q)
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return $q->when($cycleId, fn ($qq) => $qq->where('cycle_id', $cycleId));
    }
}

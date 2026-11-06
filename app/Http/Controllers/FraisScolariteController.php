<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationEcole;
use App\Models\Cycle;
use App\Models\FraisScolarite;
use Illuminate\Http\Request;

class FraisScolariteController extends Controller
{
    // Année scolaire en cours, réglée dans Paramètres
    protected function anneeCourante(): string
    {
        return ConfigurationEcole::anneeActive();
    }

    public function index()
    {
        $cycleId = \App\Helpers\PorteeCycle::pourUtilisateurConnecte();
        $cycles = Cycle::when($cycleId, fn ($q) => $q->where('id', $cycleId))->orderBy('nom')->get();

        $fraisParCycle = FraisScolarite::where('annee_scolaire', $this->anneeCourante())
            ->get()
            ->keyBy('cycle_id');

        return view('frais.index', [
            'cycles' => $cycles,
            'fraisParCycle' => $fraisParCycle,
            'anneeCourante' => $this->anneeCourante(),
        ]);
    }

    // Pour chaque cycle : montant annuel, ou échéancier (inscription + 3 tranches avec date limite).
    // Quand l'échéancier est rempli, le montant annuel est la somme des 4 montants.
    public function update(Request $request)
    {
        $echeances = array_keys(FraisScolarite::ECHEANCES);

        $regles = ['frais' => ['required', 'array'], 'frais.*.montant_annuel' => ['nullable', 'numeric', 'min:0']];
        foreach ($echeances as $code) {
            $regles["frais.*.montant_{$code}"] = ['nullable', 'numeric', 'min:0'];
            $regles["frais.*.date_{$code}"] = ['nullable', 'date'];
        }
        $request->validate($regles);
        $cycleId = \App\Helpers\PorteeCycle::pourUtilisateurConnecte();
        $cycles = Cycle::when($cycleId, fn ($q) => $q->where('id', $cycleId))->pluck('id')->all();

        foreach ($request->input('frais') as $cycleId => $saisie) {
            if (! in_array((int) $cycleId, $cycles)) {
                continue;
            }

            $valeurs = [];
            foreach ($echeances as $code) {
                $montant = $saisie["montant_{$code}"] ?? null;
                $valeurs["montant_{$code}"] = $montant === null || $montant === '' ? null : $montant;
                $valeurs["date_{$code}"] = ($saisie["date_{$code}"] ?? null) ?: null;
            }

            $montantsEcheancier = collect($valeurs)->only(array_map(fn ($c) => "montant_{$c}", $echeances))->filter(fn ($m) => $m !== null);
            $annuel = $montantsEcheancier->isNotEmpty() ? $montantsEcheancier->sum() : ($saisie['montant_annuel'] ?? null);

            // Rien de saisi pour ce cycle : on ne crée pas de ligne vide
            if (($annuel === null || $annuel === '') && ! FraisScolarite::where('cycle_id', $cycleId)->where('annee_scolaire', $this->anneeCourante())->exists()) {
                continue;
            }

            FraisScolarite::updateOrCreate(
                ['cycle_id' => $cycleId, 'annee_scolaire' => $this->anneeCourante()],
                $valeurs + ['montant_annuel' => $annuel ?: 0]
            );
        }

        return redirect()->route('frais.index')->with('success', 'Frais de scolarité mis à jour.');
    }
}

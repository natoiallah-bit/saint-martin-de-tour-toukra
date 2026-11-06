<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationEcole;
use App\Helpers\BulletinCalcul;
use App\Models\FraisScolarite;
use App\Models\Paiement;
use Illuminate\Support\Facades\Route;

class ParentDashboardController extends Controller
{
    // Année scolaire en cours, réglée dans Paramètres
    protected function anneeCourante(): string
    {
        return ConfigurationEcole::anneeActive();
    }

    public function index()
    {
        $enfants = auth()->user()->enfants()->with('classe.cycle')->get();

        $cartes = $enfants->map(function ($eleve) {
            $cycleId = $eleve->classe->cycle_id ?? null;
            $montantAnnuel = $cycleId ? FraisScolarite::montantPourCycle($cycleId, $this->anneeCourante()) : 0.0;
            $verse = Paiement::totalVerse($eleve->id, $this->anneeCourante());

            $historique = Paiement::where('eleve_id', $eleve->id)
                ->where('annee_scolaire', $this->anneeCourante())
                ->orderByDesc('date_paiement')
                ->get();

            return [
                'eleve' => $eleve,
                'montant_annuel' => $montantAnnuel,
                'verse' => $verse,
                // Enfant exonéré : rien à payer
                'restant' => $eleve->exonere ? 0.0 : max($montantAnnuel - $verse, 0),
                'historique' => $historique,
            ];
        });

        // Conseil de discipline : convocations et décisions concernant les enfants
        $dossiers = \App\Models\DossierDiscipline::with('eleve')->whereIn('eleve_id', $enfants->pluck('id'))
            ->orderByDesc('created_at')->get();

        // Certificats d'inscription délivrés par l'établissement
        $certificats = \App\Models\CertificatScolarite::whereIn('eleve_id', $enfants->pluck('id'))
            ->orderByDesc('date_delivrance')->orderByDesc('id')->get()->groupBy('eleve_id');

        return view('parent.dashboard', [
            'cartes' => $cartes,
            'alertesDiscipline' => $dossiers->filter->estNonLuParParent()->values(),
            'dossiersDiscipline' => $dossiers->groupBy('eleve_id'),
            'certificats' => $certificats,
            'aCarteScolaire' => Route::has('eleves.carte'),
            'aBulletins' => Route::has('bulletins.show'),
            'aRecu' => Route::has('paiements.recu'),
        ]);
    }
}

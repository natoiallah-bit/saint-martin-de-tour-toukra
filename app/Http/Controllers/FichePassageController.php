<?php

namespace App\Http\Controllers;

use App\Helpers\BulletinCalcul;
use App\Helpers\PorteeCycle;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;

class FichePassageController extends Controller
{
    // Choix de la classe
    public function choix()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $classes = ClasseScolaire::with('cycle')
            ->when($cycleId, fn($q) => $q->where('cycle_id', $cycleId))
            ->ordonnees()
            ->get();

        return view('fiches-passage.choix', compact('classes'));
    }

    // Fiche imprimable : une ligne par élève, moyennes T1/T2/T3/annuelle + décision
    public function afficher(ClasseScolaire $classe)
    {
        PorteeCycle::verifierClasse($classe);
        $eleves = $classe->eleves()->orderBy('nom')->orderBy('prenom')->get();

        $lignes = $eleves->map(function ($eleve) {
            $resultat = BulletinCalcul::calculer($eleve, 3);
            return [
                'eleve' => $eleve,
                'moyenneT1' => $resultat['moyenneT1'],
                'moyenneT2' => $resultat['moyenneT2'],
                'moyenneT3' => $resultat['moyenneGenerale'],
                'moyenneAnnuelle' => $resultat['moyenneAnnuelle'],
                'decision' => $resultat['decisionAnnuelle'],
            ];
        });

        // Rang sur la moyenne annuelle (celle qui décide du passage), ex aequo compris
        $annuelles = $lignes->mapWithKeys(fn ($l) => [$l['eleve']->id => $l['moyenneAnnuelle']])->all();
        $lignes = $lignes->map(fn ($l) => $l + ['rang' => BulletinCalcul::rangParmi($annuelles, $l['eleve']->id)])
            ->sortBy(fn ($l) => $l['rang'] ?? 9999)->values();

        $config = ConfigurationEcole::actuelle();

        return view('fiches-passage.afficher', [
            'classe' => $classe,
            'lignes' => $lignes,
            'anneeScolaire' => $config->annee_scolaire_active,
        ]);
    }
}

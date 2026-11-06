<?php

namespace App\Http\Controllers;

use App\Helpers\Appreciation;
use App\Helpers\Assiduite;
use App\Helpers\BulletinCalcul;
use App\Helpers\PorteeCycle;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\ConseilClasse;
use App\Models\Eleve;

class BulletinController extends Controller
{
    public function choix()
    {
        $user = auth()->user();
        $classesQuery = ClasseScolaire::with(['cycle', 'eleves' => fn ($q) => $q->orderBy('nom')->orderBy('prenom')]);

        if ($user->estLimiteAuxClassesEnseignant('bulletins')) {
            // Un enseignant ne voit, dans le choix du bulletin, que les classes
            // dont il est le professeur principal.
            $classesQuery->duProfesseurPrincipal($user);
        } else {
            $cycleId = PorteeCycle::pourUtilisateurConnecte();
            $classesQuery->when($cycleId, fn($q) => $q->where('cycle_id', $cycleId));
        }

        $classes = $classesQuery->ordonnees()->get();
        return view('bulletins.choix', compact('classes'));
    }

    public function show(Eleve $eleve, int $trimestre)
    {
        abort_unless(in_array($trimestre, [1, 2, 3]), 404);

        $user = auth()->user();

        if ($user->role === 'parent' && ! $user->estParentDe($eleve->id)) {
            abort(403, "Ce bulletin ne concerne pas l'un de vos enfants.");
        }

        if ($user->role !== 'parent') {
            PorteeCycle::verifierEleve($eleve);
        }

        if ($user->estLimiteAuxClassesEnseignant('bulletins') && ! $eleve->classe?->aPourProfesseurPrincipal($user)) {
            abort(403, "Seul le professeur principal de cette classe peut consulter ce bulletin.");
        }

        $resultat = BulletinCalcul::calculer($eleve, $trimestre);

        $cycleNom = $eleve->classe->cycle->nom ?? '';
        $estCollege = str_contains(mb_strtolower($cycleNom), 'coll');
        // Primaire et maternelle : bulletin en Verdana 9, signé par le Directeur du Primaire
        $estPrimaire = str_contains(mb_strtolower($cycleNom), 'prim') || str_contains(mb_strtolower($cycleNom), 'mater');
        // Maternelle : liste simple des matières, sans catégories (scientifique, littéraire, accessoire)
        $estMaternelle = str_contains(mb_strtolower($cycleNom), 'mater');
        $anneeScolaire = ConfigurationEcole::actuelle()->annee_scolaire_active;

        // Décisions du conseil de classe (absences, conduite, appréciation, distinction)
        $conseil = ConseilClasse::where('eleve_id', $eleve->id)
            ->where('trimestre', $trimestre)
            ->where('annee_scolaire', $anneeScolaire)
            ->first();
        $distinction = $conseil?->distinction ?: Appreciation::distinction($resultat['moyenneGenerale']);

        return view('bulletins.show', array_merge(
            [
                'eleve' => $eleve,
                'trimestre' => $trimestre,
                'estCollege' => $estCollege,
                'estPrimaire' => $estPrimaire,
                'estMaternelle' => $estMaternelle,
                'anneeScolaire' => $anneeScolaire,
                'conseil' => $conseil,
                // Absences et retards notés à l'appel pendant le trimestre (null : dates du trimestre non réglées)
                'assiduite' => Assiduite::pourEleve($eleve->id, $eleve->classe_id, $trimestre),
                'distinction' => $distinction === 'Aucune' ? null : $distinction,
                'profPrincipal' => $eleve->classe->nomProfesseurPrincipal(),
            ],
            $resultat
        ));
    }
}

<?php

namespace App\Helpers;

use App\Models\ClasseScolaire;
use App\Models\Eleve;
use App\Models\EmploiTemps;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Qui peut publier ou consulter quoi dans les cours en ligne.
 * - Un enseignant publie dans les classes et matières qu'il enseigne (emploi du temps,
 *   ou matières qui lui sont attribuées), sauf si un autre rôle lui donne plus de droits.
 * - Principal, Direction… : toutes les classes de leur cycle.
 * - Élève : sa classe. Parent : les classes de ses enfants, en lecture seule.
 */
class CoursEnLigne
{
    // Paires [classe_id, matiere_id] de l'enseignant, ou null s'il n'est pas limité
    public static function affectations(User $user, string $module = 'cours_en_ligne'): ?Collection
    {
        if (! $user->estLimiteAuxClassesEnseignant($module, 'lecture')) {
            return null;
        }

        return self::pairesEnseignant($user);
    }

    // Paires [classe_id, matiere_id] enseignées par le compte : emploi du temps et matières attribuées
    public static function pairesEnseignant(User $user): Collection
    {
        $paires = EmploiTemps::where('enseignant_id', $user->personnel_id ?? 0)
            ->get(['classe_id', 'matiere_id'])
            ->map(fn ($c) => [(int) $c->classe_id, (int) $c->matiere_id]);

        // Matières attribuées à l'enseignant : toutes les classes du cycle de la matière
        foreach (Matiere::where('enseignant_id', $user->enseignant_id ?? 0)->get() as $matiere) {
            foreach (ClasseScolaire::where('cycle_id', $matiere->cycle_id)->pluck('id') as $classeId) {
                $paires->push([(int) $classeId, (int) $matiere->id]);
            }
        }

        return $paires->unique(fn ($p) => $p[0] . '-' . $p[1])->values();
    }

    // Classes où le compte peut publier
    public static function classesGerees(User $user, string $module = 'cours_en_ligne'): Collection
    {
        $affectations = self::affectations($user, $module);
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return ClasseScolaire::with('cycle')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->when($affectations !== null, fn ($q) => $q->whereIn('id', $affectations->pluck(0)))
            ->ordonnees()
            ->get();
    }

    // Matières où le compte peut publier dans cette classe
    public static function matieresGerees(User $user, ClasseScolaire $classe, string $module = 'cours_en_ligne'): Collection
    {
        $affectations = self::affectations($user, $module);

        return Matiere::where('cycle_id', $classe->cycle_id)
            ->when($affectations !== null, fn ($q) => $q->whereIn('id', $affectations->where(0, $classe->id)->pluck(1)))
            ->orderBy('nom')
            ->get();
    }

    public static function peutGererClasse(User $user, int $classeId, string $module = 'cours_en_ligne'): bool
    {
        return self::classesGerees($user, $module)->contains('id', $classeId);
    }

    public static function peutGerer(User $user, int $classeId, int $matiereId, string $module = 'cours_en_ligne'): bool
    {
        $classe = ClasseScolaire::find($classeId);

        return $classe && self::peutGererClasse($user, $classeId, $module)
            && self::matieresGerees($user, $classe, $module)->contains('id', $matiereId);
    }

    // Élève dont le compte consulte l'espace : lui-même, ou l'enfant d'un parent
    public static function eleveConsulte(User $user, ?Eleve $enfant = null): ?Eleve
    {
        if ($user->role === 'eleve') {
            return $user->eleve;
        }
        if ($user->role === 'parent' && $enfant && $user->estParentDe($enfant->id)) {
            return $enfant;
        }

        return null;
    }
}

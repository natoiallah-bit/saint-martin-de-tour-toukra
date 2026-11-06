<?php

namespace App\Helpers;

use App\Models\ClasseScolaire;
use App\Models\Eleve;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class PorteeCycle
{
    // Retourne l'id du cycle auquel l'utilisateur connecté est limité,
    // ou null si l'utilisateur (rôle "direction") voit tous les cycles.
    public static function pourUtilisateurConnecte(): ?int
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        // La direction voit tout, quel que soit le cycle éventuellement
        // renseigné sur son compte.
        if ($user->role === 'direction') {
            return null;
        }

        return $user->cycle_id;
    }

    // Bloque l'accès (403) à un élève d'un autre cycle que celui de l'utilisateur
    public static function verifierEleve(Eleve $eleve): void
    {
        $cycleId = self::pourUtilisateurConnecte();

        abort_if($cycleId && (int) $eleve->classe?->cycle_id !== (int) $cycleId, 403, 'Cet élève ne fait pas partie de votre cycle.');
    }

    // Bloque l'accès (403) à une classe d'un autre cycle que celui de l'utilisateur
    public static function verifierClasse(ClasseScolaire $classe): void
    {
        $cycleId = self::pourUtilisateurConnecte();

        abort_if($cycleId && (int) $classe->cycle_id !== (int) $cycleId, 403, 'Cette classe ne fait pas partie de votre cycle.');
    }

    // Bloque l'accès (403) à une donnée d'un autre cycle (opération financière, personnel…).
    // Une donnée commune (sans cycle) n'est accessible qu'aux comptes sans cycle (Direction).
    public static function verifierCycle(?int $cycleIdDonnee): void
    {
        $cycleId = self::pourUtilisateurConnecte();

        abort_if($cycleId && (int) $cycleIdDonnee !== (int) $cycleId, 403, 'Cette donnée ne fait pas partie de votre cycle.');
    }

    // Règle de validation : la classe choisie doit exister et appartenir au cycle de l'utilisateur
    public static function regleClasse(): Exists
    {
        $cycleId = self::pourUtilisateurConnecte();

        return Rule::exists('classes', 'id')->when($cycleId, fn ($r) => $r->where('cycle_id', $cycleId));
    }
}

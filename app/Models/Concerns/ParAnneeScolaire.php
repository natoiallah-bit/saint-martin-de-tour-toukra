<?php

namespace App\Models\Concerns;

use App\Models\ConfigurationEcole;
use Illuminate\Database\Eloquent\Builder;

/**
 * Données rattachées à une année scolaire (notes, devoirs) : on ne lit et on
 * n'écrit que celles de l'année active réglée dans Paramètres. Au changement
 * d'année, les bulletins repartent de zéro et les anciennes notes restent en base.
 */
trait ParAnneeScolaire
{
    protected static function bootParAnneeScolaire(): void
    {
        static::addGlobalScope('annee_scolaire', function (Builder $q) {
            $q->where($q->getModel()->getTable() . '.annee_scolaire', ConfigurationEcole::anneeActive());
        });

        static::creating(function ($modele) {
            $modele->annee_scolaire ??= ConfigurationEcole::anneeActive();
        });
    }

    // Toutes les années (ex. vérifier qu'une matière n'a jamais été notée)
    public static function toutesAnnees(): Builder
    {
        return static::withoutGlobalScope('annee_scolaire');
    }
}

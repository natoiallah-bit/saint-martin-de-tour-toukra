<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    protected $fillable = ['eleve_id', 'classe_id', 'emploi_temps_id', 'duree_minutes', 'date', 'statut', 'remarque', 'saisie_par'];

    protected $casts = [
        'date' => 'date',
        'duree_minutes' => 'integer',
    ];

    // Cours concerné (vide : appel pour toute la journée)
    public function cours(): BelongsTo
    {
        return $this->belongsTo(EmploiTemps::class, 'emploi_temps_id');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(ClasseScolaire::class, 'classe_id');
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }
}

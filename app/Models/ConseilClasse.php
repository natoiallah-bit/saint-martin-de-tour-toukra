<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Décisions du conseil de classe pour un élève et un trimestre
class ConseilClasse extends Model
{
    protected $table = 'conseils_classe';

    protected $fillable = [
        'eleve_id', 'trimestre', 'annee_scolaire', 'heures_absence', 'note_conduite',
        'appreciation', 'commentaire_conduite', 'distinction',
    ];

    protected $casts = [
        'heures_absence' => 'integer',
        'note_conduite' => 'float',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}

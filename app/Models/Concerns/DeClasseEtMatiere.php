<?php

namespace App\Models\Concerns;

use App\Models\ClasseScolaire;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Contenu en ligne rattaché à une classe, une matière et l'enseignant qui l'a publié
trait DeClasseEtMatiere
{
    public function classe(): BelongsTo
    {
        return $this->belongsTo(ClasseScolaire::class, 'classe_id');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}

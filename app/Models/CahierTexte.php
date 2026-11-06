<?php

namespace App\Models;

use App\Models\Concerns\DeClasseEtMatiere;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Séance inscrite au cahier de texte : ce qui a été fait et le travail donné
class CahierTexte extends Model
{
    use DeClasseEtMatiere;

    protected $table = 'cahier_textes';

    protected $fillable = ['classe_id', 'matiere_id', 'emploi_temps_id', 'auteur_id', 'date', 'titre', 'contenu', 'travail_a_faire', 'visa', 'vise_par', 'vise_le'];

    protected $casts = ['date' => 'date', 'vise_le' => 'datetime'];

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(EmploiTemps::class, 'emploi_temps_id');
    }

    public function viseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vise_par');
    }
}

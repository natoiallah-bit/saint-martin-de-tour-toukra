<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPersonnel extends Model
{
    protected $table = 'documents_personnel';

    protected $fillable = ['personnel_id', 'type', 'nom_fichier', 'chemin_fichier'];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public static function typesDisponibles(): array
    {
        return ['Diplôme / Attestation', 'Acte de naissance', 'Carte NNI', 'CV', 'Contrat de travail', 'Casier judiciaire', 'Autre'];
    }
}

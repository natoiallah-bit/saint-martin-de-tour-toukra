<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEleve extends Model
{
    protected $table = 'documents_eleves';

    protected $fillable = ['eleve_id', 'type', 'nom_fichier', 'chemin_fichier'];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public static function typesDisponibles(): array
    {
        return ['Acte de naissance', 'Diplôme / Relevé', 'Certificat médical', 'Certificat de scolarité (ancien établissement)', 'Photo d\'identité', 'Autre'];
    }
}

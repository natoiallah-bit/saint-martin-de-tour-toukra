<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttestationTravail extends Model
{
    protected $table = 'attestations_travail';

    protected $fillable = ['personnel_id', 'numero', 'fonction', 'statut', 'date_embauche', 'en_poste', 'date_delivrance', 'motif', 'delivre_par'];

    protected $casts = ['date_embauche' => 'date', 'date_delivrance' => 'date', 'en_poste' => 'boolean'];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    public function getLibelleStatutAttribute(): ?string
    {
        return Personnel::STATUTS[$this->statut] ?? null;
    }

    // Numéro suivant pour l'année en cours : AT-2026-0001, AT-2026-0002...
    public static function prochainNumero(): string
    {
        $prefixe = 'AT-' . now()->format('Y') . '-';
        $dernier = self::where('numero', 'like', $prefixe . '%')->orderByDesc('numero')->value('numero');
        $suivant = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe . str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
    }
}

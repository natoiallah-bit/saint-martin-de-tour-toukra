<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificatScolarite extends Model
{
    protected $table = 'certificats_scolarite';

    protected $fillable = ['eleve_id', 'numero', 'annee_scolaire', 'classe_nom', 'date_delivrance', 'motif', 'delivre_par'];

    protected $casts = ['date_delivrance' => 'date'];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'delivre_par');
    }

    // Numéro suivant pour l'année en cours : CERT-2026-0001, CERT-2026-0002...
    public static function prochainNumero(): string
    {
        $prefixe = 'CERT-' . now()->format('Y') . '-';
        $dernier = self::where('numero', 'like', $prefixe . '%')->orderByDesc('numero')->value('numero');
        $suivant = $dernier ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe . str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
    }
}

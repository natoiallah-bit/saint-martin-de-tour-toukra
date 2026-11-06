<?php

namespace App\Models;

use App\Helpers\Tresorerie;
use Illuminate\Database\Eloquent\Model;

// Versement ou retrait entre deux comptes (ex. dépôt de la caisse à la banque)
class MouvementTresorerie extends Model
{
    protected $table = 'mouvements_tresorerie';

    protected $fillable = ['annee_scolaire', 'cycle_id', 'date_mouvement', 'compte_source', 'compte_destination', 'montant', 'description', 'saisi_par'];

    protected $casts = ['date_mouvement' => 'date'];

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public function getLibelleAttribute(): string
    {
        return Tresorerie::COMPTES[$this->compte_source] . ' → ' . Tresorerie::COMPTES[$this->compte_destination];
    }
}

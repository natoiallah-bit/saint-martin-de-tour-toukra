<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoldeOuverture extends Model
{
    protected $table = 'soldes_ouverture';

    protected $fillable = ['annee_scolaire', 'cycle_id', 'compte', 'montant'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enseignant extends Model
{
    protected $fillable = ['nom', 'prenom', 'telephone', 'email'];

    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ecole extends Model
{
    protected $fillable = ['nom', 'logo', 'couleur_principale', 'ville'];

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Niveau de droit d'un rôle sur un module (voir App\Helpers\Droits)
class DroitRole extends Model
{
    protected $table = 'droits_roles';

    protected $fillable = ['role', 'module', 'niveau'];

    protected $casts = ['niveau' => 'integer'];
}

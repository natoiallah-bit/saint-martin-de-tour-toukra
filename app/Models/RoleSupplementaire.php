<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Rôle ajouté à un compte en plus de son rôle principal
class RoleSupplementaire extends Model
{
    protected $table = 'roles_supplementaires';

    protected $fillable = ['user_id', 'role'];
}

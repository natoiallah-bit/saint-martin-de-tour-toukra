<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'motto',
        'address',
        'logo',
        'primary_color',
        'secondary_color',
        'accent_color',
        'license_key',
        'license_expires_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'license_expires_at' => 'datetime',
    ];
}
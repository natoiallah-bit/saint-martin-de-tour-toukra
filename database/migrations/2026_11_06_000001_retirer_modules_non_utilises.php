<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Cette école n'utilise ni les cours en ligne, ni l'infirmerie, ni la bibliothèque :
// on retire leurs droits par défaut (les tables restent, vides et inutilisées).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('droits_roles')->whereIn('module', ['cours_en_ligne', 'infirmerie', 'bibliotheque'])->delete();
        DB::table('droits_roles')->where('role', 'infirmerie')->delete();
        DB::table('roles_supplementaires')->where('role', 'infirmerie')->delete();
    }

    public function down(): void
    {
        // Rien à restaurer : ces droits ne sont plus gérés par cette version.
    }
};

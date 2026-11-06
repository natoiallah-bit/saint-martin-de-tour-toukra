<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Sigle de l'école (ex. SMT) : il commence les matricules des élèves (SMT-0001)
    public function up(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->string('sigle_ecole', 10)->default('SMT')->after('nom_ecole');
        });

        // Les matricules automatiques ELV-0001 deviennent SMT-0001 (sauf si ce numéro existe déjà)
        foreach (DB::table('eleves')->where('matricule', 'like', 'ELV-%')->get(['id', 'matricule']) as $eleve) {
            $nouveau = 'SMT-' . substr($eleve->matricule, 4);
            if (! DB::table('eleves')->where('matricule', $nouveau)->exists()) {
                DB::table('eleves')->where('id', $eleve->id)->update(['matricule' => $nouveau]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('eleves')->where('matricule', 'like', 'SMT-%')->get(['id', 'matricule']) as $eleve) {
            $ancien = 'ELV-' . substr($eleve->matricule, 4);
            if (! DB::table('eleves')->where('matricule', $ancien)->exists()) {
                DB::table('eleves')->where('id', $eleve->id)->update(['matricule' => $ancien]);
            }
        }
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->dropColumn('sigle_ecole');
        });
    }
};

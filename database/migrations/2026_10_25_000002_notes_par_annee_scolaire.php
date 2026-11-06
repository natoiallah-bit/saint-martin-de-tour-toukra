<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Notes et devoirs rattachés à l'année scolaire : au changement d'année, les bulletins repartent de zéro
    public function up(): void
    {
        $annee = DB::table('configuration_ecole')->value('annee_scolaire_active') ?? '2025-2026';

        Schema::table('notes', function (Blueprint $table) {
            $table->string('annee_scolaire', 20)->nullable()->after('trimestre');
        });
        Schema::table('evaluations', function (Blueprint $table) {
            $table->string('annee_scolaire', 20)->nullable()->after('trimestre');
        });
        DB::table('notes')->update(['annee_scolaire' => $annee]);
        DB::table('evaluations')->update(['annee_scolaire' => $annee]);

        // Nouvel index unique (il couvre aussi la clé étrangère eleve_id) avant de retirer l'ancien
        Schema::table('notes', function (Blueprint $table) {
            $table->unique(['eleve_id', 'matiere_id', 'trimestre', 'annee_scolaire'], 'notes_eleve_matiere_trimestre_annee_unique');
        });
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique(['eleve_id', 'matiere_id', 'trimestre']);
        });
        Schema::table('evaluations', function (Blueprint $table) {
            $table->index(['classe_id', 'matiere_id', 'trimestre', 'annee_scolaire'], 'evaluations_classe_matiere_trimestre_annee_index');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('evaluations_classe_matiere_trimestre_annee_index');
            $table->dropColumn('annee_scolaire');
        });
        Schema::table('notes', function (Blueprint $table) {
            $table->unique(['eleve_id', 'matiere_id', 'trimestre']);
        });
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique('notes_eleve_matiere_trimestre_annee_unique');
            $table->dropColumn('annee_scolaire');
        });
    }
};

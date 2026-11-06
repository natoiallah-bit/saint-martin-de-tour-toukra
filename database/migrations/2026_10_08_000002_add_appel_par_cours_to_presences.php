<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Appel par cours : une présence peut désormais concerner un créneau précis de
    // l'emploi du temps. Les appels existants (par journée) restent valables :
    // emploi_temps_id et duree_minutes vides = toute la journée.
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->foreignId('emploi_temps_id')->nullable()->after('classe_id')
                ->constrained('emplois_temps')->nullOnDelete();
            // Durée du cours au moment de l'appel : les absences passées ne changent
            // pas si l'emploi du temps est modifié ensuite
            $table->unsignedSmallInteger('duree_minutes')->nullable()->after('emploi_temps_id');
        });

        // Un appel par élève, par jour ET par cours. Le nouvel index est créé avant de
        // supprimer l'ancien : MySQL s'en sert pour la clé étrangère sur eleve_id.
        Schema::table('presences', function (Blueprint $table) {
            $table->unique(['eleve_id', 'date', 'emploi_temps_id'], 'presences_eleve_date_cours_unique');
        });
        Schema::table('presences', function (Blueprint $table) {
            $table->dropUnique(['eleve_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->unique(['eleve_id', 'date']);
        });
        Schema::table('presences', function (Blueprint $table) {
            $table->dropUnique('presences_eleve_date_cours_unique');
            $table->dropConstrainedForeignId('emploi_temps_id');
            $table->dropColumn('duree_minutes');
        });
    }
};

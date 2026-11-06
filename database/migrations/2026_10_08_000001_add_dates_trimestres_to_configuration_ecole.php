<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Dates de début et de fin de chaque trimestre (Paramètres) : elles servent à
    // compter les absences et retards de l'appel pour le bulletin de chaque trimestre
    public function up(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            foreach ([1, 2, 3] as $t) {
                $table->date("trimestre{$t}_debut")->nullable();
                $table->date("trimestre{$t}_fin")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            foreach ([1, 2, 3] as $t) {
                $table->dropColumn(["trimestre{$t}_debut", "trimestre{$t}_fin"]);
            }
        });
    }
};

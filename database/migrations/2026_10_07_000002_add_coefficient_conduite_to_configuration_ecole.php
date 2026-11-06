<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Coefficient de la note de conduite dans la moyenne générale, réglé par
    // l'administration dans Paramètres (1 par défaut ; 0 = non comptée)
    public function up(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->decimal('coefficient_conduite', 4, 2)->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->dropColumn('coefficient_conduite');
        });
    }
};

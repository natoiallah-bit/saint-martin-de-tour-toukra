<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Couleurs de l'école (menu, barres, boutons), tirées de son logo
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->string('couleur_principale', 7)->nullable()->after('nom_court');
            $table->string('couleur_secondaire', 7)->nullable()->after('couleur_principale');
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->dropColumn(['couleur_principale', 'couleur_secondaire']);
        });
    }
};

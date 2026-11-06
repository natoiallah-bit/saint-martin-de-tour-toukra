<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_ecole', function (Blueprint $table) {
            $table->id();
            $table->string('nom_ecole')->default('Complexe Scolaire Saint Martin de Tour de Toukra');
            $table->string('quartier')->nullable();
            $table->string('ville')->default("N'Djamena");
            $table->string('inspection')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('annee_scolaire_active')->default('2025-2026');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        // Une seule ligne de config, créée par défaut
        \DB::table('configuration_ecole')->insert([
            'nom_ecole' => 'Complexe Scolaire Saint Martin de Tour de Toukra',
            'ville' => "N'Djamena",
            'annee_scolaire_active' => '2025-2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_ecole');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emplois_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->enum('jour', ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']);
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();

            // Un seul cours à la fois pour une classe donnée, sur un créneau donné
            $table->unique(['classe_id', 'jour', 'heure_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emplois_temps');
    }
};

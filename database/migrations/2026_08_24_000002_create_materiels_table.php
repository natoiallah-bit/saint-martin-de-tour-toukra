<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materiels', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('categorie'); // Mobilier, Informatique, Pédagogique, Entretien, Sécurité...
            $table->unsignedInteger('quantite')->default(1);
            $table->enum('etat', ['bon', 'moyen', 'mauvais', 'hors_service'])->default('bon');
            $table->decimal('valeur_estimee', 10, 2)->default(0);
            $table->string('localisation')->nullable(); // Salle CM2, Bureau direction...
            $table->date('date_acquisition')->nullable();
            $table->string('remarque')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiels');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnels', function (Blueprint $table) {
            $table->id();
            $table->string('matricule')->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->enum('type', ['enseignant', 'administratif', 'securite', 'entretien', 'autre']);
            $table->string('fonction')->nullable(); // ex: "Professeur de Mathématiques", "Comptable"
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('salaire_base', 10, 2)->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnels');
    }
};

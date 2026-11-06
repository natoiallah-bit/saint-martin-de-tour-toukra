<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emprunts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livre_id')->constrained('livres')->cascadeOnDelete();
            $table->foreignId('eleve_id')->nullable()->constrained('eleves')->nullOnDelete();
            $table->string('emprunteur_nom')->nullable(); // si ce n'est pas un élève (personnel, externe)
            $table->date('date_emprunt');
            $table->date('date_retour_prevue')->nullable();
            $table->date('date_retour_effective')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emprunts');
    }
};

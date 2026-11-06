<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->unsignedTinyInteger('trimestre'); // 1, 2 ou 3
            $table->decimal('moyenne_classe', 4, 2)->nullable();
            $table->decimal('moyenne_composition', 4, 2)->nullable();
            $table->timestamps();

            // Une seule note par élève/matière/trimestre
            $table->unique(['eleve_id', 'matiere_id', 'trimestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};

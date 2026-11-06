<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frais_scolarites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->string('annee_scolaire')->default('2025-2026');
            $table->decimal('montant_annuel', 10, 2);
            $table->timestamps();

            $table->unique(['cycle_id', 'annee_scolaire']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frais_scolarites');
    }
};

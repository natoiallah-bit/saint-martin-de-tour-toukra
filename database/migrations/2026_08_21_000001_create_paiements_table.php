<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('annee_scolaire')->default('2025-2026');
            $table->enum('type', ['inscription', 'tranche1', 'tranche2', 'tranche3']);
            $table->decimal('montant', 10, 2);
            $table->date('date_paiement');
            $table->string('numero_recu')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};

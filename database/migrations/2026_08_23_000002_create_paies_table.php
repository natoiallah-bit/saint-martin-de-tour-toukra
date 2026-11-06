<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->string('mois'); // format "2026-08"
            $table->decimal('salaire_base', 10, 2);
            $table->decimal('primes', 10, 2)->default(0);
            $table->decimal('acompte', 10, 2)->default(0);
            $table->decimal('retenues', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['personnel_id', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paies');
    }
};

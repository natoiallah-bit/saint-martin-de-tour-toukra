<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->date('date');
            $table->enum('statut', ['present', 'absent', 'retard', 'conge']);
            $table->string('remarque')->nullable();
            $table->timestamps();

            $table->unique(['personnel_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointages');
    }
};

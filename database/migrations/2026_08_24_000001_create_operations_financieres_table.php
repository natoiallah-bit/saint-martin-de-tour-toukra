<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_financieres', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['recette', 'depense']);
            $table->string('categorie'); // Salaires, Fournitures, Entretien, Subvention, Don, Scolarité, Autre...
            $table->decimal('montant', 10, 2);
            $table->date('date_operation');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_financieres');
    }
};

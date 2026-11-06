<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Fiche élève : date d'entrée dans l'établissement, ancienne école, aptitude physique (EPS)
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->date('date_entree')->nullable();
            $table->string('ancienne_ecole', 200)->nullable();
            $table->string('aptitude_physique', 20)->default('apte');
            $table->string('aptitude_remarque', 500)->nullable();
            $table->date('aptitude_jusqu_au')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['date_entree', 'ancienne_ecole', 'aptitude_physique', 'aptitude_remarque', 'aptitude_jusqu_au']);
        });
    }
};

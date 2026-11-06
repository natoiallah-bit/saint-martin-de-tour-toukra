<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Nom du (de la) Responsable Administratif et Financier, qui signe les reçus
    public function up(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->string('nom_raf', 150)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->dropColumn('nom_raf');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Nom court de l'école (menu, écran de connexion), pour que l'application serve d'autres écoles
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->string('nom_court', 60)->nullable()->after('nom_ecole');
        });

        DB::table('configuration_ecole')
            ->where('nom_ecole', 'like', '%Saint Martin%')
            ->update(['nom_court' => 'Saint Martin de Tour']);
    }

    public function down(): void
    {
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->dropColumn('nom_court');
        });
    }
};

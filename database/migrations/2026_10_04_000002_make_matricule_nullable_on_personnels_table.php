<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Le matricule (PERS-0001) est calculé juste après l'insertion, à partir de l'id :
    // la colonne doit donc accepter d'être vide le temps de cette insertion.
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('matricule')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('matricule')->nullable(false)->change();
        });
    }
};

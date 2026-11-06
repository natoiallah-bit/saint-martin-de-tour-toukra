<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Numéros utiles au verso de la carte scolaire (en plus du téléphone du père)
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('mere_telephone', 30)->nullable()->after('mere_nom');
            $table->string('tuteur_telephone', 30)->nullable()->after('tuteur_nom');
        });

        // Photo du membre du personnel, imprimée sur son badge
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['mere_telephone', 'tuteur_telephone']);
        });
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};

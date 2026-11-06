<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('matricule')->unique()->nullable()->after('id');
            $table->string('lieu_naissance')->nullable()->after('date_naissance');
            $table->string('quartier')->nullable()->after('sexe');

            // Tuteur : obligatoire
            $table->string('tuteur_nom')->after('quartier');

            // Parents : optionnels
            $table->string('pere_nom')->nullable()->after('tuteur_nom');
            $table->string('pere_telephone')->nullable()->after('pere_nom');
            $table->string('pere_email')->nullable()->after('pere_telephone');
            $table->string('mere_nom')->nullable()->after('pere_email');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn([
                'matricule', 'lieu_naissance', 'quartier',
                'tuteur_nom', 'pere_nom', 'pere_telephone', 'pere_email', 'mere_nom',
            ]);
        });
    }
};

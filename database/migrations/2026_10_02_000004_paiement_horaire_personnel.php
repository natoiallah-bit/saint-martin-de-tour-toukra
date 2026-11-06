<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('personnels', 'paiement_horaire')) {
            Schema::table('personnels', function (Blueprint $table) {
                $table->boolean('paiement_horaire')->default(false)->after('salaire_base');
                $table->decimal('taux_horaire', 10, 2)->nullable()->after('paiement_horaire');
            });
        }

        if (! Schema::hasColumn('paies', 'nombre_heures')) {
            Schema::table('paies', function (Blueprint $table) {
                $table->decimal('nombre_heures', 6, 2)->nullable()->after('salaire_base');
                $table->decimal('taux_horaire', 10, 2)->nullable()->after('nombre_heures');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('personnels', 'paiement_horaire')) {
            Schema::table('personnels', function (Blueprint $table) {
                $table->dropColumn(['paiement_horaire', 'taux_horaire']);
            });
        }

        if (Schema::hasColumn('paies', 'nombre_heures')) {
            Schema::table('paies', function (Blueprint $table) {
                $table->dropColumn(['nombre_heures', 'taux_horaire']);
            });
        }
    }
};

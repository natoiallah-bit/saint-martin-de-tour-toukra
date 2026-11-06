<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations_financieres', function (Blueprint $table) {
            $table->string('mode_paiement')->nullable()->after('montant'); // Espèces, Chèque, Virement, Mobile Money...
            $table->string('piece_justificative')->nullable()->after('description'); // chemin du fichier
        });
    }

    public function down(): void
    {
        Schema::table('operations_financieres', function (Blueprint $table) {
            $table->dropColumn(['mode_paiement', 'piece_justificative']);
        });
    }
};

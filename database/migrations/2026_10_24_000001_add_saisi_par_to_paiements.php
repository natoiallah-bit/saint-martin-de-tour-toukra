<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Comptable qui a encaissé le paiement : son nom est imprimé sous la signature du reçu
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->foreignId('saisi_par')->nullable()->after('piece_justificative')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saisi_par');
        });
    }
};

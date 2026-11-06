<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Échéancier de chaque cycle : montant et date limite de l'inscription et des 3 tranches.
        // Le montant annuel devient la somme des 4 montants dès qu'ils sont renseignés.
        Schema::table('frais_scolarites', function (Blueprint $table) {
            foreach (['inscription', 'tranche1', 'tranche2', 'tranche3'] as $echeance) {
                $table->decimal("montant_{$echeance}", 12, 2)->nullable();
                $table->date("date_{$echeance}")->nullable();
            }
        });

        // Historique des relances de paiement envoyées aux familles (une ligne par enfant concerné)
        Schema::create('relances_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('annee_scolaire');
            $table->unsignedTinyInteger('niveau');          // 1 = 1re relance, 2 = 2e relance, 3 = convocation
            $table->decimal('montant_du', 12, 2);
            $table->date('date_relance');
            $table->date('date_limite')->nullable();       // date avant laquelle la famille doit régulariser
            $table->foreignId('envoyee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['eleve_id', 'annee_scolaire']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relances_paiement');
        Schema::table('frais_scolarites', function (Blueprint $table) {
            foreach (['inscription', 'tranche1', 'tranche2', 'tranche3'] as $echeance) {
                $table->dropColumn(["montant_{$echeance}", "date_{$echeance}"]);
            }
        });
    }
};

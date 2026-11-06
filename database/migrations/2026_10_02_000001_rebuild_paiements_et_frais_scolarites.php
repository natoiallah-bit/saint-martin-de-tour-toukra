<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table frais_scolarites : créée seulement si elle n'existe pas
        if (! Schema::hasTable('frais_scolarites')) {
            Schema::create('frais_scolarites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cycle_id')->constrained('cycles')->cascadeOnDelete();
                $table->string('annee_scolaire');
                $table->decimal('montant_annuel', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['cycle_id', 'annee_scolaire']);
            });
        }

        // Table paiements : créée si absente, sinon colonnes manquantes ajoutées
        if (! Schema::hasTable('paiements')) {
            Schema::create('paiements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
                $table->string('annee_scolaire');
                $table->json('types');
                $table->decimal('montant', 12, 2);
                $table->string('mode_paiement')->default('especes');
                $table->date('date_paiement');
                $table->string('numero_recu')->unique();
                $table->string('description')->nullable();
                $table->string('piece_justificative')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('paiements', function (Blueprint $table) {
                if (! Schema::hasColumn('paiements', 'types')) {
                    $table->json('types')->nullable()->after('annee_scolaire');
                }
                if (! Schema::hasColumn('paiements', 'mode_paiement')) {
                    $table->string('mode_paiement')->default('especes')->after('montant');
                }
                if (! Schema::hasColumn('paiements', 'numero_recu')) {
                    $table->string('numero_recu')->nullable()->after('date_paiement');
                }
                if (! Schema::hasColumn('paiements', 'description')) {
                    $table->string('description')->nullable();
                }
                if (! Schema::hasColumn('paiements', 'piece_justificative')) {
                    $table->string('piece_justificative')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // On ne supprime rien automatiquement pour éviter toute perte de données.
    }
};

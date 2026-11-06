<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opérations : année scolaire (totaux cohérents) et lien vers la paie qui les a créées
        Schema::table('operations_financieres', function (Blueprint $table) {
            $table->string('annee_scolaire', 20)->nullable()->after('date_operation')->index();
            $table->foreignId('paie_id')->nullable()->after('cycle_id')->constrained('paies')->cascadeOnDelete();
        });

        // Paie : date et mode de versement du salaire (sortie de caisse ou de banque)
        Schema::table('paies', function (Blueprint $table) {
            $table->date('date_paiement')->nullable()->after('mois');
            $table->string('mode_paiement', 30)->default('Espèces')->after('date_paiement');
        });

        // Logistique séparée par cycle (vide = matériel commun, géré par la Direction)
        Schema::table('materiels', function (Blueprint $table) {
            $table->foreignId('cycle_id')->nullable()->after('id')->constrained('cycles')->nullOnDelete();
        });

        // Trace de chaque correction ou suppression d'une donnée financière
        Schema::create('journal_financier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->string('objet', 30);
            $table->unsignedBigInteger('objet_id')->nullable();
            $table->string('reference', 150)->nullable();
            $table->foreignId('cycle_id')->nullable()->constrained('cycles')->nullOnDelete();
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->string('motif', 255);
            $table->timestamps();
        });

        // Trésorerie : solde de départ de chaque compte pour l'année
        Schema::create('soldes_ouverture', function (Blueprint $table) {
            $table->id();
            $table->string('annee_scolaire', 20);
            $table->foreignId('cycle_id')->nullable()->constrained('cycles')->cascadeOnDelete();
            $table->string('compte', 20);
            $table->decimal('montant', 14, 2)->default(0);
            $table->timestamps();
        });

        // Trésorerie : versements et retraits entre la caisse, la banque et le Mobile Money
        Schema::create('mouvements_tresorerie', function (Blueprint $table) {
            $table->id();
            $table->string('annee_scolaire', 20)->index();
            $table->foreignId('cycle_id')->nullable()->constrained('cycles')->nullOnDelete();
            $table->date('date_mouvement');
            $table->string('compte_source', 20);
            $table->string('compte_destination', 20);
            $table->decimal('montant', 14, 2);
            $table->string('description', 255)->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Année scolaire des opérations existantes, déduite de leur date (rentrée en septembre)
        $annee = fn (Carbon $d) => $d->month >= 9 ? $d->year . '-' . ($d->year + 1) : ($d->year - 1) . '-' . $d->year;
        foreach (DB::table('operations_financieres')->select('id', 'date_operation')->get() as $op) {
            DB::table('operations_financieres')->where('id', $op->id)
                ->update(['annee_scolaire' => $annee(Carbon::parse($op->date_operation))]);
        }

        // Les paies déjà enregistrées deviennent des dépenses « Salaires »
        $maintenant = now();
        foreach (DB::table('paies')->join('personnels', 'personnels.id', '=', 'paies.personnel_id')
            ->select('paies.*', 'personnels.nom', 'personnels.prenom', 'personnels.cycle_id')->get() as $paie) {
            $net = round($paie->salaire_base + $paie->primes - $paie->acompte - $paie->retenues, 2);
            if ($net <= 0) {
                continue;
            }
            $date = Carbon::parse($paie->created_at ?? $maintenant);
            DB::table('paies')->where('id', $paie->id)->update(['date_paiement' => $date->toDateString()]);
            DB::table('operations_financieres')->insert([
                'type' => 'depense', 'categorie' => 'Salaires', 'montant' => $net, 'mode_paiement' => 'Espèces',
                'date_operation' => $date->toDateString(), 'annee_scolaire' => $annee($date),
                'description' => 'Salaire ' . $paie->mois . ' — ' . trim($paie->nom . ' ' . $paie->prenom),
                'cycle_id' => $paie->cycle_id, 'paie_id' => $paie->id,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('operations_financieres')->whereNotNull('paie_id')->delete();
        Schema::dropIfExists('mouvements_tresorerie');
        Schema::dropIfExists('soldes_ouverture');
        Schema::dropIfExists('journal_financier');
        Schema::table('materiels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cycle_id');
        });
        Schema::table('paies', function (Blueprint $table) {
            $table->dropColumn(['date_paiement', 'mode_paiement']);
        });
        Schema::table('operations_financieres', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paie_id');
            $table->dropColumn('annee_scolaire');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Le rôle Caissier est fusionné dans Comptable : seul le comptable encaisse
        DB::table('users')->where('role', 'caissier')->update(['role' => 'comptable']);

        foreach (DB::table('roles_supplementaires')->where('role', 'caissier')->get() as $ligne) {
            $dejaComptable = DB::table('users')->where('id', $ligne->user_id)->where('role', 'comptable')->exists()
                || DB::table('roles_supplementaires')->where('user_id', $ligne->user_id)->where('role', 'comptable')->exists();
            $dejaComptable
                ? DB::table('roles_supplementaires')->where('id', $ligne->id)->delete()
                : DB::table('roles_supplementaires')->where('id', $ligne->id)->update(['role' => 'comptable']);
        }

        // Le comptable garde, pour chaque module, le plus haut des deux niveaux
        foreach (DB::table('droits_roles')->where('role', 'caissier')->get() as $droit) {
            $comptable = DB::table('droits_roles')->where('role', 'comptable')->where('module', $droit->module)->first();
            if (! $comptable) {
                DB::table('droits_roles')->insert(['role' => 'comptable', 'module' => $droit->module, 'niveau' => $droit->niveau, 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($droit->niveau > $comptable->niveau) {
                DB::table('droits_roles')->where('id', $comptable->id)->update(['niveau' => $droit->niveau, 'updated_at' => now()]);
            }
        }
        DB::table('droits_roles')->where('role', 'caissier')->delete();

        // 2. Chaque comptable travaille sur son cycle : recettes et dépenses rattachées
        //    à un cycle (vide = commun, géré par la Direction) et à la personne qui les saisit
        Schema::table('operations_financieres', function (Blueprint $table) {
            $table->foreignId('cycle_id')->nullable()->constrained('cycles')->nullOnDelete();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
        });

        // 3. Fiche du personnel : cycle d'affectation (vide = commun), date d'embauche, statut
        Schema::table('personnels', function (Blueprint $table) {
            $table->foreignId('cycle_id')->nullable()->constrained('cycles')->nullOnDelete();
            $table->date('date_embauche')->nullable();
            $table->string('statut', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cycle_id');
            $table->dropColumn(['date_embauche', 'statut']);
        });
        Schema::table('operations_financieres', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cycle_id');
            $table->dropConstrainedForeignId('saisi_par');
        });
    }
};

<?php

use App\Helpers\Droits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certificats de scolarité délivrés depuis la fiche de l'élève.
        // La classe est recopiée : le certificat reste exact après un passage en classe supérieure.
        Schema::create('certificats_scolarite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('numero', 30)->unique();
            $table->string('annee_scolaire', 20);
            $table->string('classe_nom', 100)->nullable();
            $table->date('date_delivrance');
            $table->string('motif', 200)->nullable();
            $table->foreignId('delivre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Nom du directeur, imprimé sous la signature des certificats
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->string('nom_directeur', 150)->nullable()->after('nom_raf');
        });

        $maintenant = now();
        foreach (array_keys(Droits::ROLES) as $role) {
            DB::table('droits_roles')->insertOrIgnore([
                'role' => $role, 'module' => 'certificats',
                'niveau' => Droits::DEFAUTS[$role]['certificats'] ?? Droits::AUCUN,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('module', 'certificats')->delete();
        Schema::table('configuration_ecole', function (Blueprint $table) {
            $table->dropColumn('nom_directeur');
        });
        Schema::dropIfExists('certificats_scolarite');
    }
};

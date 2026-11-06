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
        // Attestations de travail délivrées depuis la fiche du personnel.
        // Fonction et statut sont recopiés : l'attestation reste exacte s'ils changent ensuite.
        Schema::create('attestations_travail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->string('numero', 30)->unique();
            $table->string('fonction', 150)->nullable();
            $table->string('statut', 30)->nullable();
            $table->date('date_embauche')->nullable();
            $table->boolean('en_poste')->default(true);
            $table->date('date_delivrance');
            $table->string('motif', 200)->nullable();
            $table->foreignId('delivre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $maintenant = now();
        foreach (array_keys(Droits::ROLES) as $role) {
            DB::table('droits_roles')->insertOrIgnore([
                'role' => $role, 'module' => 'attestations_travail',
                'niveau' => Droits::DEFAUTS[$role]['attestations_travail'] ?? Droits::AUCUN,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('module', 'attestations_travail')->delete();
        Schema::dropIfExists('attestations_travail');
    }
};

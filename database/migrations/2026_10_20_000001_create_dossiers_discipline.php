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
        // Comparution d'un élève devant le conseil de discipline : convocation puis décision.
        // Le parent est alerté dans son espace et confirme avoir pris connaissance.
        Schema::create('dossiers_discipline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('motif', 200);
            $table->text('faits')->nullable();
            $table->date('date_faits')->nullable();
            $table->dateTime('date_conseil')->nullable();
            $table->string('lieu', 150)->nullable();
            $table->string('decision', 40)->nullable();
            $table->text('decision_details')->nullable();
            $table->date('date_decision')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('convocation_lue_le')->nullable();
            $table->dateTime('decision_lue_le')->nullable();
            $table->timestamps();
        });

        $maintenant = now();
        foreach (array_keys(Droits::ROLES) as $role) {
            DB::table('droits_roles')->insertOrIgnore([
                'role' => $role, 'module' => 'discipline',
                'niveau' => Droits::DEFAUTS[$role]['discipline'] ?? Droits::AUCUN,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('module', 'discipline')->delete();
        Schema::dropIfExists('dossiers_discipline');
    }
};

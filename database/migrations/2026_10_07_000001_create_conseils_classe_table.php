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
        // Décisions du conseil de classe, par élève et par trimestre (imprimées sur le bulletin)
        Schema::create('conseils_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->unsignedTinyInteger('trimestre');
            $table->string('annee_scolaire', 20);
            $table->unsignedSmallInteger('heures_absence')->nullable();
            $table->decimal('note_conduite', 4, 2)->nullable();
            $table->text('appreciation')->nullable();
            $table->text('commentaire_conduite')->nullable();
            // Vide = distinction calculée automatiquement d'après la moyenne générale
            $table->string('distinction', 50)->nullable();
            $table->timestamps();

            $table->unique(['eleve_id', 'trimestre', 'annee_scolaire']);
        });

        // Nouveau module "Conseil de classe" dans le tableau des droits
        $maintenant = now();
        foreach (array_keys(Droits::ROLES) as $role) {
            DB::table('droits_roles')->insertOrIgnore([
                'role' => $role,
                'module' => 'conseil_classe',
                'niveau' => Droits::DEFAUTS[$role]['conseil_classe'] ?? Droits::AUCUN,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('module', 'conseil_classe')->delete();
        Schema::dropIfExists('conseils_classe');
    }
};

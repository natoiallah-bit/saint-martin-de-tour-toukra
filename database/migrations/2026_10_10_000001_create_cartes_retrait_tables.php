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
        // Carte de retrait : une par famille (parents, quartier, enfants en charge),
        // 1 ou 2 personnes autorisées à venir chercher les enfants. La "photo" est un
        // emoji selon le sexe de la personne autorisée principale.
        Schema::create('cartes_retrait', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->nullable()->unique();
            $table->string('pere_nom', 150)->nullable();
            $table->string('pere_telephone', 30)->nullable();
            $table->string('mere_nom', 150)->nullable();
            $table->string('mere_telephone', 30)->nullable();
            $table->string('quartier', 100)->nullable();
            $table->string('responsable_nom', 150);
            $table->string('responsable_lien', 50);
            $table->enum('responsable_sexe', ['F', 'M']);
            $table->string('responsable_telephone', 30);
            $table->string('responsable_piece', 60)->nullable();
            $table->string('second_nom', 150)->nullable();
            $table->string('second_lien', 50)->nullable();
            $table->string('second_telephone', 30)->nullable();
            $table->string('second_piece', 60)->nullable();
            $table->timestamps();
        });

        // Enfants de la famille concernés par la carte
        Schema::create('carte_retrait_eleve', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carte_retrait_id')->constrained('cartes_retrait')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['carte_retrait_id', 'eleve_id']);
        });

        // Nouveau module "Cartes de retrait" dans le tableau des droits
        $maintenant = now();
        foreach (array_keys(Droits::ROLES) as $role) {
            DB::table('droits_roles')->insertOrIgnore([
                'role' => $role,
                'module' => 'cartes_retrait',
                'niveau' => Droits::DEFAUTS[$role]['cartes_retrait'] ?? Droits::AUCUN,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('module', 'cartes_retrait')->delete();
        Schema::dropIfExists('carte_retrait_eleve');
        Schema::dropIfExists('cartes_retrait');
    }
};

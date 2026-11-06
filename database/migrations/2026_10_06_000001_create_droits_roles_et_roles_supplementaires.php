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
        // Droits de chaque rôle sur chaque module (0 aucun, 1 lecture, 2 modifier, 3 supprimer)
        Schema::create('droits_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50);
            $table->string('module', 50);
            $table->unsignedTinyInteger('niveau')->default(0);
            $table->timestamps();

            $table->unique(['role', 'module']);
        });

        // Rôles en plus du rôle principal d'un compte (ex. Caissier + Surveillant)
        Schema::create('roles_supplementaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 50);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
        });

        // Droits de départ : identiques aux accès en place avant cette version
        $maintenant = now();
        $lignes = [];
        foreach (array_keys(Droits::ROLES) as $role) {
            foreach (array_keys(Droits::MODULES) as $module) {
                $lignes[] = [
                    'role' => $role,
                    'module' => $module,
                    'niveau' => Droits::DEFAUTS[$role][$module] ?? Droits::AUCUN,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            }
        }
        DB::table('droits_roles')->insert($lignes);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles_supplementaires');
        Schema::dropIfExists('droits_roles');
    }
};

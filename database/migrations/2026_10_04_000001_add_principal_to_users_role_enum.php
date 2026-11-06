<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * La colonne `role` de la table `users` est un ENUM MySQL qui liste en dur
     * les valeurs autorisées. Comme "principal" n'y figurait pas encore,
     * MySQL tronque/rejette la valeur ("Data truncated for column 'role'")
     * dès qu'on essaie de l'enregistrer, même si le code PHP (rolesValides())
     * l'autorise. Cette migration élargit l'ENUM pour inclure "principal",
     * sans toucher aux comptes existants.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM(
                'direction',
                'secretariat',
                'caissier',
                'comptable',
                'enseignant',
                'surveillant',
                'infirmerie',
                'parent',
                'principal'
            ) NOT NULL");
        } else {
            // Autres bases (SQLite des tests) : simple colonne texte, la liste des rôles
            // valides est contrôlée par l'application.
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('secretariat')->change());
        }
    }

    public function down(): void
    {
        // Retour à la liste précédente (sans "principal"). Si des comptes
        // ont déjà le rôle "principal" au moment du rollback, change
        // d'abord leur rôle avant de redescendre cette migration.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM(
                'direction',
                'secretariat',
                'caissier',
                'comptable',
                'enseignant',
                'surveillant',
                'infirmerie',
                'parent'
            ) NOT NULL");
        } else {
            // Autres bases (SQLite des tests) : simple colonne texte, la liste des rôles
            // valides est contrôlée par l'application.
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('secretariat')->change());
        }
    }
};

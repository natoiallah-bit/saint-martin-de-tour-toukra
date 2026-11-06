<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
                'direction', 'secretariat', 'caissier', 'enseignant',
                'surveillant', 'infirmerie', 'comptable'
            ) DEFAULT 'secretariat'");
        } else {
            // Autres bases (SQLite des tests) : simple colonne texte, la liste des rôles
            // valides est contrôlée par l'application.
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('secretariat')->change());
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
                'direction', 'secretariat', 'caissier', 'enseignant',
                'surveillant', 'infirmerie'
            ) DEFAULT 'secretariat'");
        } else {
            // Autres bases (SQLite des tests) : simple colonne texte, la liste des rôles
            // valides est contrôlée par l'application.
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('secretariat')->change());
        }
    }
};

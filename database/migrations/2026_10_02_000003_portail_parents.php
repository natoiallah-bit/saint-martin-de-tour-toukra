<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
                'direction', 'secretariat', 'caissier', 'enseignant',
                'surveillant', 'infirmerie', 'comptable', 'parent'
            ) DEFAULT 'secretariat'");
        } else {
            // Autres bases (SQLite des tests) : simple colonne texte, la liste des rôles
            // valides est contrôlée par l'application.
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('secretariat')->change());
        }

        if (! Schema::hasTable('parent_eleve')) {
            Schema::create('parent_eleve', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'eleve_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_eleve');

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
};

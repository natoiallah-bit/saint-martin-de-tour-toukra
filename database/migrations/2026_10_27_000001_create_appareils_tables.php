<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mode hors ligne de l'application téléphone / ordinateur :
// - appareils : un téléphone ou un PC connecté avec un compte (jeton de connexion) ;
// - envois_appareils : chaque saisie reçue d'un appareil, pour ne jamais l'enregistrer deux fois.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appareils', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nom', 100);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jeton_hash', 64)->nullable()->unique();
            $table->timestamp('derniere_synchro')->nullable();
            $table->timestamps();
        });

        Schema::create('envois_appareils', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('appareil_id')->nullable()->constrained('appareils')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('reference', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envois_appareils');
        Schema::dropIfExists('appareils');
    }
};

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
        // Comptes élèves : nouveau rôle "eleve", relié à la fiche de l'élève
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM(
                'direction', 'secretariat', 'caissier', 'comptable', 'enseignant',
                'surveillant', 'infirmerie', 'parent', 'principal', 'eleve'
            ) NOT NULL");
        }
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('eleve_id')->nullable()->unique()->constrained('eleves')->cascadeOnDelete();
        });

        // Leçons (supports de cours) d'une classe et d'une matière
        Schema::create('lecons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre', 200);
            $table->text('contenu')->nullable();
            $table->string('lien_video', 500)->nullable();
            $table->boolean('publiee')->default(true);
            $table->timestamps();
        });

        // Devoirs à rendre et travaux rendus par les élèves
        Schema::create('devoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre', 200);
            $table->text('consigne')->nullable();
            $table->dateTime('date_limite');
            $table->unsignedSmallInteger('note_sur')->default(20);
            $table->timestamps();
        });
        Schema::create('rendus_devoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devoir_id')->constrained('devoirs')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->text('texte')->nullable();
            $table->dateTime('rendu_le');
            $table->decimal('note', 5, 2)->nullable();
            $table->text('commentaire')->nullable();
            $table->dateTime('corrige_le')->nullable();
            $table->timestamps();
            $table->unique(['devoir_id', 'eleve_id']);
        });

        // Classes virtuelles : cours en direct avec un lien de visioconférence
        Schema::create('seances_en_ligne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre', 200);
            $table->text('description')->nullable();
            $table->dateTime('debut');
            $table->unsignedSmallInteger('duree_minutes')->default(60);
            $table->string('lien', 500);
            $table->timestamps();
        });

        // Quiz (QCM) corrigés automatiquement
        Schema::create('quiz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre', 200);
            $table->text('consigne')->nullable();
            $table->dateTime('ouverture')->nullable();
            $table->dateTime('fermeture')->nullable();
            $table->unsignedSmallInteger('duree_minutes')->nullable();
            $table->boolean('publie')->default(true);
            $table->timestamps();
        });
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quiz')->cascadeOnDelete();
            $table->text('enonce');
            $table->decimal('points', 5, 2)->default(1);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
        Schema::create('quiz_choix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->string('texte', 500);
            $table->boolean('correct')->default(false);
            $table->timestamps();
        });
        Schema::create('quiz_tentatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quiz')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->json('reponses')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('total', 6, 2)->nullable();
            $table->dateTime('commence_le');
            $table->dateTime('soumis_le')->nullable();
            $table->boolean('hors_delai')->default(false);
            $table->timestamps();
            $table->unique(['quiz_id', 'eleve_id']);
        });

        // Fichiers joints aux leçons, aux devoirs et aux travaux rendus
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('chemin');
            $table->string('nom_original');
            $table->unsignedInteger('taille')->default(0);
            $table->timestamps();
        });

        // Nouveau module dans le tableau des droits
        $maintenant = now();
        foreach (array_keys(Droits::ROLES) as $role) {
            DB::table('droits_roles')->insertOrIgnore([
                'role' => $role, 'module' => 'cours_en_ligne',
                'niveau' => Droits::DEFAUTS[$role]['cours_en_ligne'] ?? Droits::AUCUN,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('module', 'cours_en_ligne')->delete();
        foreach (['pieces_jointes', 'quiz_tentatives', 'quiz_choix', 'quiz_questions', 'quiz', 'seances_en_ligne', 'rendus_devoirs', 'devoirs', 'lecons'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eleve_id');
        });
    }
};

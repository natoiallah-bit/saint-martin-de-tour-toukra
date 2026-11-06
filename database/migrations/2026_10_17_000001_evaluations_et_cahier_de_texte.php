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
        // Devoirs d'une classe dans une matière et un trimestre, chacun avec son pourcentage
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->unsignedTinyInteger('trimestre');
            $table->string('libelle', 100);
            $table->decimal('poids', 5, 2);
            $table->date('date')->nullable();
            $table->timestamps();
            $table->index(['classe_id', 'matiere_id', 'trimestre']);
        });
        Schema::create('notes_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->decimal('note', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'eleve_id']);
        });

        // Les anciennes notes « Devoir 1 / Devoir 2 » deviennent deux devoirs à 50 %
        $anciennes = DB::table('notes')
            ->join('eleves', 'eleves.id', '=', 'notes.eleve_id')
            ->where(fn ($q) => $q->whereNotNull('notes.devoir1')->orWhereNotNull('notes.devoir2'))
            ->whereNotNull('eleves.classe_id')
            ->get(['notes.eleve_id', 'notes.matiere_id', 'notes.trimestre', 'notes.devoir1', 'notes.devoir2', 'eleves.classe_id']);
        $maintenant = now();
        foreach ($anciennes->groupBy(fn ($n) => $n->classe_id . '-' . $n->matiere_id . '-' . $n->trimestre) as $groupe) {
            $premier = $groupe->first();
            foreach (['devoir1' => 'Devoir 1', 'devoir2' => 'Devoir 2'] as $colonne => $libelle) {
                $evaluationId = DB::table('evaluations')->insertGetId([
                    'classe_id' => $premier->classe_id, 'matiere_id' => $premier->matiere_id, 'trimestre' => $premier->trimestre,
                    'libelle' => $libelle, 'poids' => 50, 'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
                foreach ($groupe as $note) {
                    if ($note->{$colonne} !== null) {
                        DB::table('notes_evaluations')->insert([
                            'evaluation_id' => $evaluationId, 'eleve_id' => $note->eleve_id, 'note' => $note->{$colonne},
                            'created_at' => $maintenant, 'updated_at' => $maintenant,
                        ]);
                    }
                }
            }
        }

        // Cahier de texte : ce que l'enseignant a fait à chaque séance
        Schema::create('cahier_textes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('emploi_temps_id')->nullable()->constrained('emplois_temps')->nullOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('titre', 200);
            $table->text('contenu')->nullable();
            $table->text('travail_a_faire')->nullable();
            $table->text('visa')->nullable();
            $table->foreignId('vise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('vise_le')->nullable();
            $table->timestamps();
            $table->index(['classe_id', 'matiere_id', 'date']);
        });

        $maintenant = now();
        foreach (array_keys(Droits::ROLES) as $role) {
            DB::table('droits_roles')->insertOrIgnore([
                'role' => $role, 'module' => 'cahier_texte',
                'niveau' => Droits::DEFAUTS[$role]['cahier_texte'] ?? Droits::AUCUN,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('droits_roles')->where('module', 'cahier_texte')->delete();
        Schema::dropIfExists('cahier_textes');
        Schema::dropIfExists('notes_evaluations');
        Schema::dropIfExists('evaluations');
    }
};

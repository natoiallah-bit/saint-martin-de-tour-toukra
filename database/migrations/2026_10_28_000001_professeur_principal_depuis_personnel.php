<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Le professeur principal se choisit désormais parmi le Personnel (type Enseignant),
// comme dans l'emploi du temps. Les choix faits avec l'ancienne liste « Enseignants »
// sont repris quand un membre du personnel porte le même nom et prénom.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('prof_principal_personnel_id')->nullable()->after('prof_principal_id')
                ->constrained('personnels')->nullOnDelete();
        });

        $normaliser = fn ($texte) => mb_strtolower(trim((string) $texte));
        $personnels = DB::table('personnels')->where('type', 'enseignant')->get(['id', 'nom', 'prenom']);

        $classes = DB::table('classes')->join('enseignants', 'enseignants.id', '=', 'classes.prof_principal_id')
            ->get(['classes.id', 'enseignants.nom', 'enseignants.prenom']);
        foreach ($classes as $classe) {
            $correspondants = $personnels->filter(fn ($p) => $normaliser($p->nom) === $normaliser($classe->nom)
                && $normaliser($p->prenom) === $normaliser($classe->prenom));
            if ($correspondants->count() === 1) {
                DB::table('classes')->where('id', $classe->id)->update(['prof_principal_personnel_id' => $correspondants->first()->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prof_principal_personnel_id');
        });
    }
};

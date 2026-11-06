<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

// Devoir noté d'une classe (matière, trimestre), avec son pourcentage dans la moyenne des devoirs
class Evaluation extends Model
{
    use Concerns\ParAnneeScolaire;

    protected $fillable = ['classe_id', 'matiere_id', 'trimestre', 'annee_scolaire', 'libelle', 'poids', 'date'];

    protected $casts = ['poids' => 'float', 'date' => 'date'];

    public function notes(): HasMany
    {
        return $this->hasMany(NoteEvaluation::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(ClasseScolaire::class, 'classe_id');
    }

    public static function pour(int $classeId, int $matiereId, int $trimestre): Collection
    {
        return self::where('classe_id', $classeId)->where('matiere_id', $matiereId)
            ->where('trimestre', $trimestre)->orderBy('date')->orderBy('id')->get();
    }

    /**
     * Moyenne des devoirs d'un élève : chaque note compte selon son pourcentage.
     * Un devoir non noté (absence, devoir pas encore corrigé) est mis de côté et les
     * autres pourcentages sont ramenés sur 100. Ex. 40 % → 12 et 60 % → 15 donne 13,8.
     */
    public static function moyennePonderee(Collection $evaluations, array $notesParEvaluation): ?float
    {
        $points = 0;
        $poids = 0;
        foreach ($evaluations as $evaluation) {
            $note = $notesParEvaluation[$evaluation->id] ?? null;
            if ($note !== null && $evaluation->poids > 0) {
                $points += $note * $evaluation->poids;
                $poids += $evaluation->poids;
            }
        }

        return $poids > 0 ? round($points / $poids, 2) : null;
    }
}

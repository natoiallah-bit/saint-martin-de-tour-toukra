<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploiTemps extends Model
{
    protected $table = 'emplois_temps';

    protected $fillable = ['classe_id', 'jour', 'heure_debut', 'heure_fin', 'matiere_id', 'enseignant_id'];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(ClasseScolaire::class, 'classe_id');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'enseignant_id');
    }

    // Durée du créneau en minutes
    public function dureeMinutes(): int
    {
        return (int) \Carbon\Carbon::parse($this->heure_debut)->diffInMinutes(\Carbon\Carbon::parse($this->heure_fin));
    }

    // Jour de la semaine d'une date, au format de l'emploi du temps (lundi... samedi ; null le dimanche)
    public static function jourDe(\DateTimeInterface|string $date): ?string
    {
        return self::jours()[\Carbon\Carbon::parse($date)->dayOfWeekIso - 1] ?? null;
    }

    public static function jours(): array
    {
        return ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    }
}

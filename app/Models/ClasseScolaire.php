<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasseScolaire extends Model
{
    protected $table = 'classes';

    protected $fillable = ['cycle_id', 'nom', 'prof_principal', 'prof_principal_id', 'prof_principal_personnel_id'];

    // Ordre des cycles, du plus petit au plus grand
    protected const CYCLES = ['maternelle' => 1, 'primaire' => 2, 'college' => 3, 'lycee' => 4];

    // Niveaux reconnus dans le nom de la classe (expression → rang), testés dans cet ordre
    protected const NIVEAUX = [
        '/^(petite section|ps\b|tps\b)/' => 1,
        '/^(moyenne section|ms\b)/' => 2,
        '/^(grande section|gs\b)/' => 3,
        '/^(sil|cp\s*1)/' => 10,
        '/^cp\s*2/' => 11,
        '/^cp\b/' => 10,
        '/^ce\s*1/' => 12,
        '/^ce\s*2/' => 13,
        '/^ce\b/' => 12,
        '/^cm\s*1/' => 14,
        '/^cm\s*2/' => 15,
        '/^cm\b/' => 14,
        '/^(6|six)/' => 20,
        '/^(5|cinq)/' => 21,
        '/^(4|quat)/' => 22,
        '/^(3|trois)/' => 23,
        '/^(2|seconde)/' => 30,
        '/^(1|prem)/' => 31,
        '/^(t|term)/' => 32,
    ];

    protected static function booted(): void
    {
        // L'ordre suit le nom de la classe et son cycle : recalculé à chaque enregistrement
        static::saving(function (ClasseScolaire $classe) {
            $classe->ordre = self::calculerOrdre($classe->nom, Cycle::find($classe->cycle_id)?->nom);
        });
    }

    // Rang d'une classe : cycle × 100 + niveau. Ex. CM2 du primaire = 215, 6ème A du collège = 320.
    public static function calculerOrdre(?string $nomClasse, ?string $nomCycle): int
    {
        $normaliser = fn (?string $texte) => (string) \Illuminate\Support\Str::of((string) $texte)->ascii()->lower()->squish();

        $cycle = 5;
        foreach (self::CYCLES as $motCle => $rang) {
            if (str_contains($normaliser($nomCycle), $motCle)) {
                $cycle = $rang;
                break;
            }
        }

        $niveau = 99;
        foreach (self::NIVEAUX as $motif => $rang) {
            if (preg_match($motif, $normaliser($nomClasse))) {
                $niveau = $rang;
                break;
            }
        }

        return $cycle * 100 + $niveau;
    }

    // Tri des classes dans l'ordre de l'école, puis par nom (6ème A avant 6ème B)
    public function scopeOrdonnees(Builder $query): Builder
    {
        return $query->orderBy('classes.ordre')->orderBy('classes.nom');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function eleves(): HasMany
    {
        return $this->hasMany(Eleve::class, 'classe_id');
    }

    // Ancienne liste « Enseignants » : gardée pour les classes pas encore mises à jour
    public function profPrincipal(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'prof_principal_id');
    }

    // Professeur principal : un membre du Personnel de type Enseignant
    public function professeurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'prof_principal_personnel_id');
    }

    public function nomProfesseurPrincipal(): ?string
    {
        $prof = $this->professeurPrincipal ?? $this->profPrincipal;

        return $prof ? trim($prof->prenom . ' ' . $prof->nom) : ($this->prof_principal ?: null);
    }

    // Classes dont le compte est le professeur principal (fiche Personnel liée au compte,
    // ou ancienne fiche Enseignant si la classe n'a pas encore été mise à jour)
    public function scopeDuProfesseurPrincipal(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('classes.prof_principal_personnel_id', $user->personnel_id ?? 0)
                ->orWhere(fn ($q) => $q->whereNull('classes.prof_principal_personnel_id')
                    ->where('classes.prof_principal_id', $user->enseignant_id ?? 0));
        });
    }

    public function aPourProfesseurPrincipal(User $user): bool
    {
        if ($this->prof_principal_personnel_id) {
            return $user->personnel_id && (int) $this->prof_principal_personnel_id === (int) $user->personnel_id;
        }

        return $user->enseignant_id && (int) $this->prof_principal_id === (int) $user->enseignant_id;
    }
}

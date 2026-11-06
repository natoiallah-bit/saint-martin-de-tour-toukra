<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Helpers\Droits;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'cycle_id', 'enseignant_id', 'personnel_id', 'eleve_id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    // Fiche enseignant liée (affectation des matières)
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    // Fiche personnel liée (paie, pointage, emploi du temps)
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    // Fiche de l'élève pour un compte élève (cours en ligne)
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    // Enfants rattachés à ce compte (uniquement pertinent pour le rôle "parent")
    public function enfants(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'parent_eleve', 'user_id', 'eleve_id');
    }

    public function estParentDe(int $eleveId): bool
    {
        if ($this->role !== 'parent') {
            return false;
        }
        return $this->enfants()->where('eleve_id', $eleveId)->exists();
    }

    // Rôles en plus du rôle principal (ex. un comptable qui est aussi surveillant)
    public function rolesSupplementaires(): HasMany
    {
        return $this->hasMany(RoleSupplementaire::class);
    }

    // Tous les rôles du compte : le rôle principal, puis les rôles supplémentaires
    public function roles(): array
    {
        return array_values(array_unique(array_merge(
            [$this->role],
            $this->exists ? $this->rolesSupplementaires->pluck('role')->all() : []
        )));
    }

    public function aRole(string ...$roles): bool
    {
        return count(array_intersect($roles, $this->roles())) > 0;
    }

    // Droits de chaque rôle du compte, chargés une seule fois par requête
    protected ?array $droitsParRole = null;

    protected function droitsParRole(): array
    {
        return $this->droitsParRole ??= DroitRole::whereIn('role', $this->roles())
            ->get()
            ->groupBy('role')
            ->map(fn ($lignes) => $lignes->pluck('niveau', 'module')->all())
            ->all();
    }

    // Niveau de droit sur un module (0 à 3, voir App\Helpers\Droits).
    // Plusieurs rôles : on garde le niveau le plus élevé. $sauf permet d'ignorer un rôle.
    public function niveau(string $module, ?string $sauf = null): int
    {
        if ($this->role === 'direction') {
            return Droits::SUPPRIMER;
        }
        // Parents et élèves n'ont accès qu'à leur propre espace
        if (in_array($this->role, ['parent', 'eleve'], true)) {
            return Droits::AUCUN;
        }

        $niveau = Droits::AUCUN;
        foreach ($this->droitsParRole() as $role => $droits) {
            // Un comptable sans cycle n'a aucun droit de comptable (il verrait les comptes de tous les cycles)
            if ($role === 'comptable' && ! $this->cycle_id) {
                continue;
            }
            if ($role !== $sauf) {
                $niveau = max($niveau, (int) ($droits[$module] ?? Droits::AUCUN));
            }
        }

        return $niveau;
    }

    // Ex. : $user->peut('scolarite', 'supprimer')
    public function peut(string $module, string $niveau = 'lecture'): bool
    {
        return $this->niveau($module) >= Droits::niveau($niveau);
    }

    // Un enseignant ne voit que ses classes et matières, sauf si un autre de ses
    // rôles lui donne déjà ce droit sur tout le module (ex. Enseignant + Secrétariat)
    public function estLimiteAuxClassesEnseignant(string $module, string $niveau = 'lecture'): bool
    {
        return $this->aRole('enseignant')
            && $this->niveau($module, 'enseignant') < Droits::niveau($niveau);
    }

    // Première page que le compte a le droit d'ouvrir (après la connexion)
    public function pageAccueil(): ?string
    {
        if ($this->role === 'parent') {
            return 'parent.dashboard';
        }
        if ($this->peutVoirDashboard() || $this->role === 'principal') {
            return 'dashboard';
        }

        // Page habituelle du rôle principal, si le compte y a toujours droit
        $pageDuRole = match ($this->role) {
            'enseignant' => ['emplois_temps', 'emplois-temps.choix'],
            'surveillant' => ['pointage', 'pointage.index'],
            'comptable' => ['comptabilite', 'operations.index'],
            default => null,
        };
        if ($pageDuRole && $this->peut($pageDuRole[0])) {
            return $pageDuRole[1];
        }

        foreach (Droits::PAGES as $module => $route) {
            if ($this->peut($module)) {
                return $route;
            }
        }

        return null;
    }

    // Adresse de la page d'accueil du compte (lien « Accueil » de toutes les pages)
    public function urlAccueil(): string
    {
        $route = $this->pageAccueil();

        return $route && \Illuminate\Support\Facades\Route::has($route) ? route($route) : url('/');
    }

    // Libellés de tous les rôles du compte (ex. "Caissier, Surveillant")
    public function getLibelleRolesAttribute(): string
    {
        return collect($this->roles())->map(fn ($r) => self::libelleDuRole($r))->implode(', ');
    }

    public static function libelleDuRole(string $role): string
    {
        return match ($role) {
            'direction' => 'Direction',
            'parent' => 'Parent',
            'eleve' => 'Élève',
            default => Droits::ROLES[$role] ?? $role,
        };
    }

    public function getLibelleRoleAttribute(): string
    {
        return match ($this->role) {
            'direction' => 'Direction',
            'secretariat' => 'Secrétariat',
            'comptable' => 'Comptable',
            'enseignant' => 'Enseignant',
            'surveillant' => 'Surveillant',
            'parent' => 'Parent',
            'principal' => 'Principal',
            'eleve' => 'Élève',
            default => $this->role,
        };
    }

    // Rôles autorisés à voir le tableau de bord global (vue d'ensemble complète)
    public function peutVoirDashboard(): bool
    {
        return $this->aRole('direction', 'secretariat');
    }
}

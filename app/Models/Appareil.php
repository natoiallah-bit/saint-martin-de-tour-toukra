<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// Un téléphone ou un ordinateur où l'application est installée (mode hors ligne)
class Appareil extends Model
{
    protected $table = 'appareils';

    protected $fillable = ['uuid', 'nom', 'user_id', 'jeton_hash', 'derniere_synchro'];

    protected $hidden = ['jeton_hash'];

    protected function casts(): array
    {
        return ['derniere_synchro' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Nouveau jeton à chaque connexion : l'ancien ne marche plus. Seule son empreinte est gardée.
    public static function connecter(string $uuid, string $nom, User $user): string
    {
        $jeton = Str::random(60);
        $appareil = static::firstOrNew(['uuid' => $uuid]);
        $appareil->fill(['nom' => $nom, 'user_id' => $user->id, 'jeton_hash' => hash('sha256', $jeton)])->save();

        return $jeton;
    }

    public static function parJeton(?string $jeton): ?self
    {
        return $jeton ? static::with('user')->where('jeton_hash', hash('sha256', $jeton))->first() : null;
    }
}

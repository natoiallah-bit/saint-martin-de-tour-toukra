<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Trace des corrections et suppressions de données financières (consultée par la Direction)
class JournalFinancier extends Model
{
    protected $table = 'journal_financier';

    protected $fillable = ['user_id', 'action', 'objet', 'objet_id', 'reference', 'cycle_id', 'avant', 'apres', 'motif'];

    protected $casts = ['avant' => 'array', 'apres' => 'array'];

    public const OBJETS = [
        'paiement' => 'Paiement de scolarité',
        'operation' => 'Opération comptable',
        'paie' => 'Paie du personnel',
        'mouvement' => 'Versement / retrait',
        'solde' => 'Solde de départ',
    ];

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }

    public static function noter(string $action, string $objet, ?Model $modele, string $motif, ?array $avant = null, ?array $apres = null, ?string $reference = null, ?int $cycleId = null): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'objet' => $objet,
            'objet_id' => $modele?->getKey(),
            'reference' => $reference,
            'cycle_id' => $cycleId,
            'avant' => $avant,
            'apres' => $apres,
            'motif' => $motif,
        ]);
    }

    // Champs réellement modifiés : [avant, après]
    public static function differences(array $avant, array $apres): array
    {
        $a = [];
        $b = [];
        foreach ($apres as $champ => $valeur) {
            $ancien = $avant[$champ] ?? null;
            if ((string) json_encode($ancien) !== (string) json_encode($valeur) && ! (is_numeric($ancien) && is_numeric($valeur) && (float) $ancien === (float) $valeur)) {
                $a[$champ] = $ancien;
                $b[$champ] = $valeur;
            }
        }

        return [$a, $b];
    }

    public function getLibelleObjetAttribute(): string
    {
        return self::OBJETS[$this->objet] ?? $this->objet;
    }
}

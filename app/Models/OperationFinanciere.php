<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationFinanciere extends Model
{
    protected $table = 'operations_financieres';
    protected $fillable = ['type', 'categorie', 'montant', 'mode_paiement', 'date_operation', 'description', 'piece_justificative', 'cycle_id', 'saisi_par', 'annee_scolaire', 'paie_id'];

    // Cycle de l'opération (vide = dépense ou recette commune, gérée par la Direction)
    public function cycle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function auteur(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    // Bulletin de paie à l'origine de la dépense (salaires ajoutés automatiquement)
    public function paie(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Paie::class);
    }

    public const MODES = ['Espèces', 'Chèque', 'Virement bancaire', 'Mobile Money', 'Autre'];


    protected $casts = [
        'date_operation' => 'date',
    ];

    // Sans précision, une opération appartient à l'année scolaire active
    protected static function booted(): void
    {
        static::creating(function (self $operation) {
            $operation->annee_scolaire ??= ConfigurationEcole::anneeActive();
        });
    }

    // Catégories suggérées par défaut (l'utilisateur peut aussi taper une catégorie libre)
    public static function categoriesRecette(): array
    {
        return ['Scolarité (hors élèves)', 'Subvention', 'Don', 'Location de salle', 'Autre recette'];
    }

    public static function categoriesDepense(): array
    {
        // Les salaires sont ajoutés automatiquement depuis la Paie du personnel
        return ['Fournitures scolaires', 'Entretien / Réparation', 'Électricité / Eau', 'Carburant', 'Achat matériel', 'Autre dépense'];
    }
}

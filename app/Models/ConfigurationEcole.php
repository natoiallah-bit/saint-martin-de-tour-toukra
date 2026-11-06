<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigurationEcole extends Model
{
    protected $table = 'configuration_ecole';

    protected $fillable = [
        'nom_ecole', 'nom_court', 'couleur_principale', 'couleur_secondaire', 'sigle_ecole', 'quartier', 'ville', 'inspection', 'telephone',
        'email', 'nom_raf', 'nom_directeur', 'annee_scolaire_active', 'logo_path', 'coefficient_conduite',
        'trimestre1_debut', 'trimestre1_fin', 'trimestre2_debut', 'trimestre2_fin', 'trimestre3_debut', 'trimestre3_fin',
    ];

    // Toujours la même ligne (id 1), créée à l'installation
    public static function actuelle(): self
    {
        return self::first() ?? self::create([]);
    }

    protected $casts = [
        'trimestre1_debut' => 'date', 'trimestre1_fin' => 'date',
        'trimestre2_debut' => 'date', 'trimestre2_fin' => 'date',
        'trimestre3_debut' => 'date', 'trimestre3_fin' => 'date',
    ];

    // Dates [début, fin] d'un trimestre, ou null si elles ne sont pas réglées dans Paramètres
    public static function periodeTrimestre(int $trimestre): ?array
    {
        $config = self::actuelle();
        $debut = $config->{"trimestre{$trimestre}_debut"};
        $fin = $config->{"trimestre{$trimestre}_fin"};

        return $debut && $fin ? [$debut, $fin] : null;
    }

    // Coefficient de la note de conduite dans la moyenne générale (0 = non comptée)
    public static function coefficientConduite(): float
    {
        return (float) (self::actuelle()->coefficient_conduite ?? 1);
    }

    // Année scolaire en cours (ex. "2026-2027"), modifiable dans Paramètres
    public static function anneeActive(): string
    {
        return self::actuelle()->annee_scolaire_active;
    }
}

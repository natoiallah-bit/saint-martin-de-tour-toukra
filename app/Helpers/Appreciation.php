<?php

namespace App\Helpers;

// Appréciations et distinctions calculées d'après une moyenne sur 20
class Appreciation
{
    // Distinctions que le conseil de classe peut choisir à la place du calcul automatique
    public const DISTINCTIONS = [
        'Félicitations',
        'Encouragements',
        'Tableau d\'honneur',
        'Avertissement',
        'Blâme',
        'Aucune',
    ];

    public static function pour(?float $moyenne): ?string
    {
        return match (true) {
            $moyenne === null => null,
            $moyenne >= 16 => 'Excellent',
            $moyenne >= 14 => 'Très bien',
            $moyenne >= 12 => 'Bien',
            $moyenne >= 10 => 'Assez bien',
            default => 'Insuffisant',
        };
    }

    // Commentaire de conduite déduit de la note (le conseil peut écrire le sien à la place)
    public static function conduite($note): ?string
    {
        if ($note === null || $note === '') {
            return null;
        }
        $note = (float) $note;

        return match (true) {
            $note >= 16 => 'Excellente conduite',
            $note >= 14 => 'Très bonne conduite',
            $note >= 12 => 'Bonne conduite',
            $note >= 10 => 'Conduite passable',
            default => 'Conduite à améliorer',
        };
    }

    // Distinction automatique (pas de sanction automatique : c'est au conseil de la décider)
    public static function distinction(?float $moyenne): ?string
    {
        return match (true) {
            $moyenne === null => null,
            $moyenne >= 16 => 'Félicitations',
            $moyenne >= 14 => 'Encouragements',
            $moyenne >= 12 => 'Tableau d\'honneur',
            default => null,
        };
    }
}

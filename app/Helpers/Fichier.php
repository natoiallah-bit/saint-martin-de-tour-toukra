<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;

// Fichiers envoyés (photos, documents, pièces justificatives).
// Ils sont rangés dans storage/app/private/uploads/..., hors du dossier public :
// on ne peut les ouvrir qu'en passant par la route "fichiers.afficher", qui exige
// une connexion. En base, on garde le même format de chemin qu'avant
// ("uploads/dossier/nom.ext"), donc les anciens enregistrements restent valables.
class Fichier
{
    public static function enregistrer(UploadedFile $fichier, string $dossier, string $prefixe): string
    {
        // Extension déduite du contenu réel du fichier (pas du nom envoyé)
        $extension = $fichier->guessExtension() ?: strtolower($fichier->getClientOriginalExtension());
        $nomStocke = uniqid($prefixe) . '.' . preg_replace('/[^a-z0-9]/', '', strtolower($extension));
        $fichier->move(self::racinePrivee() . '/uploads/' . $dossier, $nomStocke);

        return 'uploads/' . $dossier . '/' . $nomStocke;
    }

    // Chemin complet sur le disque, ou null si le fichier n'existe pas.
    // Les fichiers envoyés avant cette version peuvent encore être dans public/uploads
    // (tant que "php artisan fichiers:proteger" n'a pas été lancé) : on les retrouve aussi.
    public static function chemin(?string $relatif): ?string
    {
        if (!$relatif || !str_starts_with($relatif, 'uploads/') || str_contains($relatif, '..')) {
            return null;
        }

        foreach ([self::racinePrivee() . '/' . $relatif, public_path($relatif)] as $chemin) {
            if (is_file($chemin)) {
                return $chemin;
            }
        }

        return null;
    }

    public static function existe(?string $relatif): bool
    {
        return self::chemin($relatif) !== null;
    }

    public static function url(string $relatif): string
    {
        return route('fichiers.afficher', ['chemin' => $relatif]);
    }

    public static function supprimer(?string $relatif): void
    {
        $chemin = self::chemin($relatif);
        if ($chemin) {
            unlink($chemin);
        }
    }

    public static function racinePrivee(): string
    {
        return storage_path('app/private');
    }
}

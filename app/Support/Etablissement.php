<?php

namespace App\Support;

use App\Models\ConfigurationEcole;
use Illuminate\Support\Str;

// Identité de l'école (nom, adresse, téléphones, logos), réglée dans Paramètres.
// La même application sert plusieurs écoles : rien du nom d'une école n'est écrit dans les pages.
class Etablissement
{
    // Une petite requête par appel : les Paramètres modifiés s'appliquent tout de suite
    public static function config(): ConfigurationEcole
    {
        return ConfigurationEcole::actuelle();
    }

    public static function nom(): string
    {
        return trim((string) self::config()->nom_ecole) ?: 'Établissement scolaire';
    }

    // Nom affiché dans le menu, l'écran de connexion et le titre des pages
    public static function nomCourt(): string
    {
        return trim((string) self::config()->nom_court) ?: self::nom();
    }

    // Initiales affichées à la place de l'emblème tant qu'il n'est pas envoyé (ex. SMT)
    public static function initiales(): string
    {
        $mots = preg_split('/[\s\-]+/u', Str::ascii(self::nomCourt()), -1, PREG_SPLIT_NO_EMPTY);
        $mots = array_filter($mots, fn ($m) => ! in_array(Str::lower($m), ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'l', 'd'], true));

        return Str::upper(collect($mots)->take(3)->map(fn ($m) => Str::substr($m, 0, 1))->implode('')) ?: 'E';
    }

    // Couleurs de l'interface tirées du logo (Paramètres), par défaut une palette neutre :
    // principale = menu et barres, secondaire = boutons et touches de couleur
    public static function couleurs(): array
    {
        $config = self::config();
        $hex = fn ($valeur, $defaut) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $valeur) ? strtolower($valeur) : $defaut;
        $principale = $hex($config->couleur_principale, '#312c63');
        $secondaire = $hex($config->couleur_secondaire, '#c9973b');

        return [
            'principale' => $principale,
            'principale_900' => self::melanger($principale, '#ffffff', 0.10),
            'principale_800' => self::melanger($principale, '#ffffff', 0.22),
            'principale_clair' => self::melanger($principale, '#ffffff', 0.90),
            'principale_texte' => self::melanger($principale, '#ffffff', 0.75),
            'secondaire' => $secondaire,
            'secondaire_400' => self::melanger($secondaire, '#ffffff', 0.18),
        ];
    }

    // Mélange de deux couleurs #rrggbb (part = proportion de la seconde)
    private static function melanger(string $a, string $b, float $part): string
    {
        $canal = fn ($c, $i) => hexdec(substr($c, 1 + 2 * $i, 2));

        return '#' . implode('', array_map(
            fn ($i) => sprintf('%02x', (int) round($canal($a, $i) * (1 - $part) + $canal($b, $i) * $part)),
            [0, 1, 2]
        ));
    }

    // L'installation d'origine garde ses logos et ses coordonnées par défaut
    public static function adresse(): string
    {
        $config = self::config();
        $quartier = trim((string) $config->quartier);

        return collect([$quartier, trim((string) $config->ville)])->filter()->implode(', ');
    }

    public static function telephone(): string
    {
        return trim((string) self::config()->telephone);
    }

    // Bannière des documents (bulletins, reçus, certificats) : adresse de l'image, ou null
    public static function banniere(): ?string
    {
        return self::image('banniere.png');
    }

    // Emblème carré (menu, connexion, cartes) : adresse de l'image, ou null
    public static function embleme(): ?string
    {
        return self::image('embleme.png');
    }

    // Photo de l'école sur le tableau de bord
    public static function photo(): ?string
    {
        return self::image('photo.jpg');
    }

    // Images envoyées dans Paramètres (public/images/ecole)
    private static function image(string $envoyee): ?string
    {
        $chemin = 'images/ecole/' . $envoyee;
        if (file_exists(public_path($chemin))) {
            return asset($chemin) . '?v=' . filemtime(public_path($chemin));
        }

        return null;
    }
}

<?php

namespace App\Helpers;

class NombreEnLettres
{
    protected static array $unites = [
        0 => '', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq',
        6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix',
        11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze', 15 => 'quinze',
        16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf',
    ];

    protected static array $dizaines = [
        2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante',
        6 => 'soixante', 7 => 'soixante', 8 => 'quatre-vingt', 9 => 'quatre-vingt',
    ];

    public static function convertir(int $nombre): string
    {
        if ($nombre === 0) {
            return 'zéro';
        }

        if ($nombre < 0) {
            return 'moins ' . self::convertir(abs($nombre));
        }

        return trim(self::convertirGroupe($nombre));
    }

    protected static function convertirGroupe(int $n): string
    {
        if ($n < 0) {
            return '';
        }

        if ($n < 20) {
            return self::$unites[$n];
        }

        if ($n < 100) {
            return self::convertirDizaine($n);
        }

        if ($n < 1000) {
            return self::convertirCentaine($n);
        }

        if ($n < 1000000) {
            return self::convertirMille($n);
        }

        if ($n < 1000000000) {
            return self::convertirMillion($n);
        }

        return self::convertirMilliard($n);
    }

    protected static function convertirDizaine(int $n): string
    {
        $d = intdiv($n, 10);
        $u = $n % 10;

        if ($d === 7 || $d === 9) {
            // soixante-dix / quatre-vingt-dix : base + 10..19
            $base = self::$dizaines[$d];
            $reste = 10 + $u;
            $mot = self::$unites[$reste];
            // soixante-et-onze
            if ($u === 1 && $d === 7) {
                return $base . '-et-' . $mot;
            }
            return $base . '-' . $mot;
        }

        if ($u === 0) {
            if ($d === 8) {
                return self::$dizaines[$d] . 's';
            }
            return self::$dizaines[$d];
        }

        if ($u === 1 && $d !== 8) {
            return self::$dizaines[$d] . '-et-un';
        }

        return self::$dizaines[$d] . '-' . self::$unites[$u];
    }

    protected static function convertirCentaine(int $n): string
    {
        $c = intdiv($n, 100);
        $reste = $n % 100;

        $prefixe = $c === 1 ? 'cent' : self::$unites[$c] . ' cent';

        if ($reste === 0) {
            return $c > 1 ? $prefixe . 's' : $prefixe;
        }

        return $prefixe . ' ' . self::convertirGroupe($reste);
    }

    protected static function convertirMille(int $n): string
    {
        $mille = intdiv($n, 1000);
        $reste = $n % 1000;

        $prefixe = $mille === 1 ? 'mille' : self::convertirGroupe($mille) . ' mille';

        if ($reste === 0) {
            return $prefixe;
        }

        return $prefixe . ' ' . self::convertirGroupe($reste);
    }

    protected static function convertirMillion(int $n): string
    {
        $million = intdiv($n, 1000000);
        $reste = $n % 1000000;

        $prefixe = ($million === 1 ? 'un million' : self::convertirGroupe($million) . ' millions');

        if ($reste === 0) {
            return $prefixe;
        }

        return $prefixe . ' ' . self::convertirGroupe($reste);
    }

    protected static function convertirMilliard(int $n): string
    {
        $milliard = intdiv($n, 1000000000);
        $reste = $n % 1000000000;

        $prefixe = ($milliard === 1 ? 'un milliard' : self::convertirGroupe($milliard) . ' milliards');

        if ($reste === 0) {
            return $prefixe;
        }

        return $prefixe . ' ' . self::convertirGroupe($reste);
    }

    /**
     * Convertit un montant en francs CFA en toutes lettres,
     * ex: 125000 -> "cent vingt-cinq mille francs CFA"
     */
    public static function montantFcfa(float $montant): string
    {
        $entier = (int) round($montant);

        if ($entier === 0) {
            return 'zéro franc CFA';
        }

        $lettres = self::convertir($entier);
        $suffixe = $entier > 1 ? 'francs CFA' : 'franc CFA';

        return ucfirst($lettres) . ' ' . $suffixe;
    }
}

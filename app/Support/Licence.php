<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Clé d'activation : "charge.signature" en base64url. La charge est un JSON
 * {"v":1,"sigle":"SMT","ecole":"…","fin":"AAAA-MM-JJ"} signé en Ed25519 par l'auteur.
 * La vérification se fait hors ligne avec la clé publique (config/licence.php).
 *
 * Etats : valide, bientot (fin proche), expiree (tolérance), bloquee (lecture seule).
 * Sans clé : essai de quelques jours depuis l'installation, puis mêmes étapes.
 */
class Licence
{
    public static function appliquee(): bool
    {
        return trim((string) config('licence.cle_publique')) !== '';
    }

    // Lit et vérifie une clé. Retourne ['ecole', 'sigle', 'fin' => Carbon] ou lève une \RuntimeException lisible.
    public static function lire(string $cle, ?string $sigle = null): array
    {
        $publique = base64_decode((string) config('licence.cle_publique'), true);
        if ($publique === false || strlen($publique) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \RuntimeException("La clé publique de l'application est mal configurée.");
        }

        $parties = explode('.', preg_replace('/\s+/', '', $cle));
        if (count($parties) !== 2 || $parties[0] === '' || $parties[1] === '') {
            throw new \RuntimeException("Clé d'activation incomplète : copiez-la en entier.");
        }
        $charge = self::decoder($parties[0]);
        $signature = self::decoder($parties[1]);
        if ($charge === null || $signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $charge, $publique)) {
            throw new \RuntimeException("Clé d'activation invalide (non reconnue).");
        }

        $donnees = json_decode($charge, true);
        if (! is_array($donnees) || ($donnees['v'] ?? null) !== 1 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($donnees['fin'] ?? ''))) {
            throw new \RuntimeException("Clé d'activation illisible.");
        }

        $sigle ??= (string) Etablissement::config()->sigle_ecole;
        if (strcasecmp((string) ($donnees['sigle'] ?? ''), $sigle) !== 0) {
            throw new \RuntimeException("Cette clé est établie pour l'école « " . ($donnees['sigle'] ?? '?') . " », pas pour « {$sigle} ».");
        }

        return [
            'ecole' => (string) ($donnees['ecole'] ?? ''),
            'sigle' => (string) $donnees['sigle'],
            'fin' => Carbon::createFromFormat('Y-m-d', $donnees['fin'])->startOfDay(),
        ];
    }

    // Etat actuel de la licence de cette installation
    public static function etat(): array
    {
        if (! self::appliquee()) {
            return ['statut' => 'valide', 'source' => 'desactivee', 'fin' => null, 'jours' => null, 'message' => null, 'erreur' => null];
        }

        $config = Etablissement::config();
        $source = 'essai';
        $erreur = null;
        $fin = ($config->created_at ?? Carbon::now())->copy()->startOfDay()->addDays((int) config('licence.essai_jours'));

        if (filled($config->licence_cle)) {
            try {
                $fin = self::lire($config->licence_cle)['fin'];
                $source = 'cle';
            } catch (\RuntimeException $e) {
                $erreur = $e->getMessage();
            }
        }

        $jours = (int) Carbon::today()->diffInDays($fin, false);
        $statut = match (true) {
            $jours > (int) config('licence.avertissement_jours') => 'valide',
            $jours >= 0 => 'bientot',
            -$jours <= (int) config('licence.tolerance_jours') => 'expiree',
            default => 'bloquee',
        };

        return [
            'statut' => $statut, 'source' => $source, 'fin' => $fin, 'jours' => $jours,
            'erreur' => $erreur, 'message' => self::message($statut, $source, $jours, $fin),
        ];
    }

    private static function message(string $statut, string $source, int $jours, Carbon $fin): ?string
    {
        $quoi = $source === 'essai' ? "La période d'essai" : "La licence";
        $date = $fin->format('d/m/Y');
        $tolerance = (int) config('licence.tolerance_jours');

        return match ($statut) {
            'bientot' => $jours === 0 ? "{$quoi} se termine aujourd'hui ({$date})." : "{$quoi} se termine dans {$jours} jour" . ($jours > 1 ? 's' : '') . " ({$date}).",
            'expiree' => "{$quoi} est terminée depuis le {$date}. Renouvelez-la : dans " . ($tolerance + $jours) . " jour" . (($tolerance + $jours) > 1 ? 's' : '') . ", l'application passera en lecture seule.",
            'bloquee' => "{$quoi} est terminée depuis le {$date} : l'application est en lecture seule. Les données restent consultables et sauvegardables ; renouvelez la licence pour pouvoir les modifier.",
            default => null,
        };
    }

    private static function decoder(string $texte): ?string
    {
        $brut = base64_decode(strtr($texte, '-_', '+/'), true);

        return $brut === false ? null : $brut;
    }
}

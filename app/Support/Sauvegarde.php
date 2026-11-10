<?php

namespace App\Support;

use App\Models\ClasseScolaire;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\Personnel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Sauvegarde complète : base de données, photos et documents, images de l'école, fiche de contrôle.
 * Utilisée par `php artisan ecole:sauvegarder` (clé USB) et par la page Sauvegarde (téléchargement ZIP).
 */
class Sauvegarde
{
    // Crée la sauvegarde dans $dossier. Retourne les lignes de la fiche de contrôle ; lève une \RuntimeException lisible.
    public static function creer(string $dossier): array
    {
        File::ensureDirectoryExists($dossier);
        $connexion = config('database.default');
        $config = config("database.connections.$connexion");

        // 1. Base de données
        if ($config['driver'] === 'sqlite') {
            // VACUUM INTO : copie cohérente même si l'application est ouverte
            DB::statement('VACUUM INTO ?', [$dossier . '/database.sqlite']);
        } else {
            $mysqldump = env('MYSQLDUMP_PATH') ?: collect(glob('C:/wamp64/bin/mysql/mysql*/bin/mysqldump.exe') ?: [])->last() ?: 'mysqldump';
            $cible = $dossier . '/base.sql';
            $commande = [$mysqldump, '--host=' . $config['host'], '--port=' . $config['port'], '--user=' . $config['username'],
                '--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file=' . $cible, $config['database']];
            if ((string) $config['password'] !== '') {
                array_splice($commande, 4, 0, ['--password=' . $config['password']]);
            }
            $resultat = Process::run($commande);
            if (! $resultat->successful() || ! is_file($cible)) {
                throw new \RuntimeException('Export MySQL impossible : ' . trim($resultat->errorOutput()) . ' (le serveur MySQL est-il démarré ?)');
            }
        }

        // 2. Photos, justificatifs et documents, puis images de l'école (bannière, emblème, photo)
        if (is_dir(storage_path('app'))) {
            File::copyDirectory(storage_path('app'), $dossier . '/storage-app');
        }
        if (is_dir(public_path('images/ecole'))) {
            File::copyDirectory(public_path('images/ecole'), $dossier . '/images-ecole');
        }

        // 3. Fiche de contrôle : ce que contient la sauvegarde
        $resume = [
            'Sauvegarde du ' . now()->format('d/m/Y à H:i'),
            'Base : ' . ($config['driver'] === 'sqlite' ? 'SQLite (database.sqlite)' : 'MySQL (base.sql)'),
            'Élèves : ' . Eleve::count(),
            'Classes : ' . ClasseScolaire::count(),
            'Paiements : ' . Paiement::count(),
            'Personnel : ' . Personnel::count(),
            '',
            'Restaurer : voir INSTALLATION.md, partie « Sauvegarde et restauration ».',
        ];
        file_put_contents($dossier . '/LISEZMOI.txt', implode(PHP_EOL, $resume) . PHP_EOL);

        return $resume;
    }

    // Nom du dossier ou du fichier de sauvegarde, ex. Sauvegardes-SaintMartinDeTour
    public static function nomDossier(): string
    {
        return 'Sauvegardes-' . \Illuminate\Support\Str::studly(\Illuminate\Support\Str::slug(Etablissement::nomCourt()));
    }

    // Sauvegarde empaquetée en ZIP : retourne le chemin du fichier temporaire (à supprimer après envoi)
    public static function zip(): string
    {
        $temp = sys_get_temp_dir() . '/sauvegarde-' . bin2hex(random_bytes(6));
        $zip = $temp . '.zip';

        try {
            self::creer($temp . '/contenu');
            $archive = new \ZipArchive();
            if ($archive->open($zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Impossible de créer l'archive ZIP.");
            }
            $base = $temp . '/contenu';
            $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterateur as $fichier) {
                if ($fichier->isFile()) {
                    $archive->addFile($fichier->getPathname(), substr($fichier->getPathname(), strlen($base) + 1));
                }
            }
            $archive->close();
        } catch (\Throwable $e) {
            @unlink($zip);
            throw $e;
        } finally {
            File::deleteDirectory($temp);
        }

        return $zip;
    }
}

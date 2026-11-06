<?php

use Illuminate\Foundation\Inspiring;
use App\Support\AdresseReseau;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// À lancer une fois après la mise à jour : déplace les fichiers déjà envoyés
// (public/uploads) vers le stockage privé, où ils ne sont plus accessibles sans connexion.
// Les chemins enregistrés en base ne changent pas.
Artisan::command('fichiers:proteger', function () {
    $source = public_path('uploads');
    $destination = \App\Helpers\Fichier::racinePrivee() . '/uploads';

    if (!is_dir($source)) {
        $this->info('Aucun fichier à déplacer.');
        return;
    }

    $deplaces = 0;
    $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    foreach ($fichiers as $fichier) {
        if (!$fichier->isFile()) {
            continue;
        }
        $relatif = substr($fichier->getPathname(), strlen($source) + 1);
        $cible = $destination . '/' . $relatif;

        if (!is_dir(dirname($cible))) {
            mkdir(dirname($cible), 0755, true);
        }
        if (rename($fichier->getPathname(), $cible)) {
            $deplaces++;
        } else {
            $this->error('Impossible de déplacer : ' . $relatif);
        }
    }

    $this->info("$deplaces fichier(s) déplacé(s) vers le stockage privé.");
})->purpose('Met les photos et documents envoyés à l\'abri de l\'accès public');


// Affiché par demarrer.bat : l'adresse à taper dans l'application (téléphone ou ordinateur)
Artisan::command('ecole:adresses {--port=8000}', function () {
    $port = (int) $this->option('port');
    $urls = AdresseReseau::urls($port);

    $this->newLine();
    $this->line('  Sur CET ordinateur (navigateur ou application Windows) :');
    $this->info("      http://127.0.0.1:{$port}");
    $this->newLine();
    if ($urls) {
        $this->line('  Sur les TELEPHONES et autres PC du meme Wi-Fi, adresse du serveur :');
        foreach ($urls as $url) {
            $this->info("      {$url}");
        }
    } else {
        $this->warn("  Aucun reseau detecte : connectez cet ordinateur au Wi-Fi de l'ecole.");
    }
    $this->newLine();
})->purpose("Affiche l'adresse du serveur pour les applications");

// Sauvegarde sur clé USB (sauvegarder.bat) : la base de données et les fichiers envoyés
// (photos, justificatifs), dans un dossier daté. Rien n'est modifié dans l'application.
Artisan::command('ecole:sauvegarder {destination : dossier ou lettre de la clé, ex. E:\\}', function () {
    $racine = rtrim(str_replace('\\', '/', (string) $this->argument('destination')), '/');
    if ($racine === '' || ! is_dir($racine . '/')) {
        $this->error("Dossier introuvable : {$this->argument('destination')}. La clé USB est-elle branchée ?");
        return 1;
    }

    $dossier = $racine . '/Sauvegardes-' . Str::studly(Str::slug(\App\Support\Etablissement::nomCourt())) . '/' . now()->format('Y-m-d_H\hi');
    \Illuminate\Support\Facades\File::ensureDirectoryExists($dossier);
    $connexion = config('database.default');
    $config = config("database.connections.$connexion");

    // 1. Base de données
    if ($config['driver'] === 'sqlite') {
        // VACUUM INTO : copie cohérente même si l'application est ouverte
        $cible = $dossier . '/database.sqlite';
        \Illuminate\Support\Facades\DB::statement('VACUUM INTO ?', [$cible]);
        $this->info('Base SQLite copiée.');
    } else {
        $mysqldump = env('MYSQLDUMP_PATH') ?: collect(glob('C:/wamp64/bin/mysql/mysql*/bin/mysqldump.exe') ?: [])->last() ?: 'mysqldump';
        $cible = $dossier . '/base.sql';
        $commande = [$mysqldump, '--host=' . $config['host'], '--port=' . $config['port'], '--user=' . $config['username'],
            '--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file=' . $cible, $config['database']];
        if ((string) $config['password'] !== '') {
            array_splice($commande, 4, 0, ['--password=' . $config['password']]);
        }
        $resultat = \Illuminate\Support\Facades\Process::run($commande);
        if (! $resultat->successful() || ! is_file($cible)) {
            $this->error('Export MySQL impossible : ' . trim($resultat->errorOutput()) . ' (WAMP est-il démarré ?)');
            return 1;
        }
        $this->info('Base MySQL exportée (base.sql).');
    }

    // 2. Photos, justificatifs et documents
    if (is_dir(storage_path('app'))) {
        \Illuminate\Support\Facades\File::copyDirectory(storage_path('app'), $dossier . '/storage-app');
        $this->info('Photos et documents copiés.');
    }

    // 3. Fiche de contrôle : ce que contient la sauvegarde
    $resume = [
        'Sauvegarde du ' . now()->format('d/m/Y à H:i'),
        'Base : ' . ($config['driver'] === 'sqlite' ? 'SQLite (database.sqlite)' : 'MySQL (base.sql)'),
        'Élèves : ' . \App\Models\Eleve::count(),
        'Classes : ' . \App\Models\ClasseScolaire::count(),
        'Paiements : ' . \App\Models\Paiement::count(),
        'Personnel : ' . \App\Models\Personnel::count(),
        '',
        'Restaurer : voir INSTALLATION.md, partie « Sauvegarde et restauration ».',
    ];
    file_put_contents($dossier . '/LISEZMOI.txt', implode(PHP_EOL, $resume) . PHP_EOL);

    $this->newLine();
    $this->info('Sauvegarde terminée : ' . str_replace('/', DIRECTORY_SEPARATOR, $dossier));
    $this->line('  ' . $resume[2] . ' · ' . $resume[3] . ' · ' . $resume[4]);
})->purpose('Sauvegarde la base et les fichiers sur une clé USB');

// Identité d'une nouvelle école, avant sa première connexion (le reste se règle dans Paramètres)
Artisan::command('ecole:identite {nom : nom complet, ex. "Complexe Scolaire Saint Martin de Tours"}
    {--court= : nom du menu, ex. "Saint Martin de Tours"}
    {--sigle= : début des matricules, ex. SMT}
    {--quartier=} {--ville=} {--telephone=} {--email=}
    {--couleur= : couleur principale du logo, ex. #1f3a60}
    {--couleur2= : couleur secondaire, ex. #a3201f}', function () {
    $config = \App\Models\ConfigurationEcole::actuelle();
    $donnees = ['nom_ecole' => trim($this->argument('nom'))];
    foreach (['couleur' => 'couleur_principale', 'couleur2' => 'couleur_secondaire'] as $option => $colonne) {
        if ($this->option($option) !== null) {
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $this->option($option))) {
                $this->error("Couleur invalide : {$this->option($option)} (format #rrggbb).");

                return 1;
            }
            $donnees[$colonne] = strtolower($this->option($option));
        }
    }
    foreach (['court' => 'nom_court', 'quartier' => 'quartier', 'ville' => 'ville', 'telephone' => 'telephone', 'email' => 'email'] as $option => $colonne) {
        if ($this->option($option) !== null) {
            $donnees[$colonne] = trim($this->option($option));
        }
    }
    if ($this->option('sigle') !== null) {
        $sigle = strtoupper(trim($this->option('sigle')));
        if (! preg_match('/^[A-Z0-9]{1,10}$/', $sigle)) {
            $this->error('Le sigle ne doit contenir que des lettres et des chiffres (10 au plus).');

            return 1;
        }
        $donnees['sigle_ecole'] = $sigle;
    }
    $config->update($donnees);
    \App\Models\Ecole::query()->update(['nom' => $donnees['nom_ecole']] + (isset($donnees['ville']) ? ['ville' => $donnees['ville']] : []));

    $this->info('École enregistrée : ' . \App\Support\Etablissement::nom() . ' (' . \App\Support\Etablissement::nomCourt() . ')');
    $this->line('Ajoutez la bannière, l\'emblème et la photo dans Paramètres.');
})->purpose('Règle le nom et les coordonnées d\'une nouvelle école');

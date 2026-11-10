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

    $dossier = $racine . '/' . \App\Support\Sauvegarde::nomDossier() . '/' . now()->format('Y-m-d_H\hi');
    try {
        $resume = \App\Support\Sauvegarde::creer($dossier);
    } catch (\RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->newLine();
    $this->info('Sauvegarde terminée : ' . str_replace('/', DIRECTORY_SEPARATOR, $dossier));
    $this->line('  ' . $resume[2] . ' · ' . $resume[3] . ' · ' . $resume[4]);
})->purpose('Sauvegarde la base et les fichiers sur une clé USB');

// Premier compte Direction d'un hébergement en ligne (aucun compte par défaut avec mot de passe connu).
// Sans argument, lit ECOLE_ADMIN_EMAIL, ECOLE_ADMIN_MOT_DE_PASSE et ECOLE_ADMIN_NOM. Ne fait rien si une Direction existe déjà.
Artisan::command('ecole:premier-compte {email? : adresse de connexion} {motdepasse? : 10 caractères au moins} {--nom=}', function () {
    $email = trim((string) ($this->argument('email') ?? env('ECOLE_ADMIN_EMAIL', '')));
    $motdepasse = (string) ($this->argument('motdepasse') ?? env('ECOLE_ADMIN_MOT_DE_PASSE', ''));
    $nom = trim((string) ($this->option('nom') ?? env('ECOLE_ADMIN_NOM', ''))) ?: 'Direction';

    if (\App\Models\User::where('role', 'direction')->exists()) {
        $this->line('Un compte Direction existe déjà : rien à faire.');

        return 0;
    }
    if ($email === '' || $motdepasse === '') {
        $this->warn('Aucun compte Direction. Définissez ECOLE_ADMIN_EMAIL et ECOLE_ADMIN_MOT_DE_PASSE, ou lancez : php artisan ecole:premier-compte EMAIL MOTDEPASSE');

        return 0;
    }
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Adresse e-mail invalide.');

        return 1;
    }
    if (mb_strlen($motdepasse) < 10) {
        $this->error('Le mot de passe doit avoir 10 caractères au moins.');

        return 1;
    }

    \App\Models\User::create(['name' => $nom, 'email' => $email, 'password' => \Illuminate\Support\Facades\Hash::make($motdepasse), 'role' => 'direction']);
    $this->info("Compte Direction créé : {$email}");
})->purpose('Crée le premier compte Direction (hébergement en ligne)');

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

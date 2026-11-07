# Saint Martin de Tour : installation et applications

L'application fonctionne de deux façons au choix. Rien n'oblige à changer celle déjà utilisée.

- **Avec WAMP** (comme aujourd'hui) : base MySQL, adresse `http://localhost/...` ou un hôte virtuel.
- **Sans WAMP** (nouveau) : un PC de l'école sert de serveur avec PHP seul et une base SQLite (`database/database.sqlite`).

## Quel ordinateur installer ?

- **Un seul PC est le serveur.** Il garde toutes les données : élèves, notes, paiements. Si WAMP tourne déjà avec vos données, **ce PC est votre serveur** et il n'y a rien à réinstaller.
- **Les autres ordinateurs et les téléphones** n'installent que l'application (`.exe` ou `.apk`), ou utilisent un navigateur, avec l'adresse du serveur. Ils voient alors les mêmes données.
- **`installer.bat` crée un nouveau serveur avec une base vide.** Il crée l'école, les cycles Maternelle, Primaire, Collège et Lycée, les matières de base et les comptes. Utilisez-le seulement pour un PC serveur sans WAMP : les données d'un autre serveur n'y sont pas.

## Installation sans WAMP (PC serveur Windows)

1. Il faut PHP 8.3 ou plus. PHP peut venir de WAMP, de Laragon ou de XAMPP, ou bien être installé seul.
2. Double-cliquez sur **`installer.bat`**. Internet est nécessaire la première fois, pour télécharger les composants.
   - Si une extension PHP manque, le message indique la ligne à activer dans `php.ini`. Les extensions utilisées sont gd, zip, pdo_sqlite, etc.
   - Comptes créés : `direction@saintmartin.td` et `secretariat@saintmartin.td`, mot de passe `MotDePasse123`. **Changez ce mot de passe** après la première connexion.
3. Double-cliquez sur **`demarrer.bat`** et laissez la fenêtre ouverte. Elle affiche :
   - l'adresse pour ce PC : `http://127.0.0.1:8000` ;
   - l'adresse pour les téléphones et les autres PC du même Wi-Fi, par exemple `http://192.168.1.20:8000`.

   Si Windows le demande, autorisez PHP sur les **réseaux privés**.
4. Pour une mise à jour, utilisez **`mettre-a-jour.bat`**. Les données sont conservées.

Sur Linux ou Mac, utilisez `./installer.sh` puis `./demarrer.sh`.

> L'adresse du PC peut changer quand on change de Wi-Fi. Relisez-la dans la fenêtre de `demarrer.bat`.

## Identité de l'école

`installer.bat` règle tout seul l'identité de l'école : **Complexe Scolaire Saint Martin de Tour de Toukra**, nom court « Saint Martin de Tour », sigle **SMT** (matricules `SMT-0001`, reçus `SMT-2026-0001`).

À compléter après la première connexion, dans **Paramètres** :

- le quartier, la ville, les téléphones et l'e-mail ;
- la **bannière** des documents (image en largeur en haut des bulletins et des reçus) ;
- l'**emblème** (logo carré du menu, de la connexion et des cartes) ;
- la **photo** de l'école pour le tableau de bord ;
- les couleurs du logo (principale foncée pour le menu, secondaire pour les boutons).

Tant qu'une image manque, le nom et l'adresse s'impriment en texte et les initiales de l'école remplacent l'emblème. Les images envoyées sont gardées dans `public/images/ecole` et ne sont pas écrasées par les mises à jour.

Pour changer le nom ou le sigle en ligne de commande :

```
php artisan ecole:identite "Nom complet" --court="Nom court" --sigle=SMT --quartier="…" --ville="N'Djamena" --telephone="66 00 00 00"
```

## Licence et clé d'activation

Le logiciel est sous licence propriétaire (fichier `LICENSE`) : l'école l'utilise, l'auteur en reste propriétaire. Les données de l'école lui appartiennent toujours.

**Pour l'école (Direction)**
- Une **période d'essai de 30 jours** démarre à l'installation.
- Ensuite, il faut une **clé d'activation**, fournie par l'auteur : menu **Système, Licence**, collez la clé et enregistrez.
- Un bandeau prévient **30 jours avant la fin**. Après la fin, **30 jours de tolérance** (bandeau rouge), puis l'application passe en **lecture seule** : tout reste consultable et sauvegardable, mais rien ne peut plus être modifié jusqu'au renouvellement. La connexion et la page Licence restent toujours ouvertes.
- La clé est liée au **sigle de l'école** (SMT). Si le sigle change dans Paramètres, il faut une nouvelle clé.

**Pour l'auteur : fabriquer une clé**
```
php outils/licence.php generer SMT "Complexe Scolaire Saint Martin de Tour de Toukra" 2027-11-06 licence-privee.key
```
La date est le dernier jour de validité. La clé obtenue est envoyée à l'école (WhatsApp, e-mail).

- `licence-privee.key` est la clé qui **signe** les licences : gardez-la secrète, sauvegardez-la en plusieurs endroits, ne la mettez **jamais** sur GitHub (elle est dans `.gitignore`) ni chez l'école. Perdue, plus aucune clé ne peut être émise sans changer l'application.
- Pour votre propre paire de clés : `php outils/licence.php paire`, puis collez la clé publique affichée dans `config/licence.php` (`cle_publique`).
- Vérifier une clé : `php outils/licence.php verifier "CLE"`.

Ce contrôle est un moyen de dissuasion et de gestion des renouvellements, pas une protection absolue : quelqu'un qui modifie le code peut le retirer. La vraie protection reste le contrat et la licence écrite.

## Sauvegarde et restauration (clé USB)

**Faites une sauvegarde chaque jour**, sur le PC serveur, et gardez la clé hors de l'école.

1. Branchez la clé USB et notez sa lettre (par exemple `E`).
2. Double-cliquez sur **`sauvegarder.bat`**, puis tapez la lettre de la clé.
3. Attendez le message « Sauvegarde terminée ». Il donne le nombre d'élèves, de classes, de paiements et de membres du personnel sauvegardés.

La sauvegarde se trouve sur la clé dans `Sauvegardes-SaintMartinDeTour\<date_heure>\` :

- `database.sqlite` (serveur sans WAMP) ou `base.sql` (WAMP / MySQL) : toutes les données ;
- `storage-app\` : photos, documents et justificatifs ;
- `LISEZMOI.txt` : date et contenu de la sauvegarde.

Avec WAMP, WAMP doit être démarré (icône verte). En ligne de commande : `php artisan ecole:sauvegarder E:\`.

### Restaurer une sauvegarde

Choisissez le dossier daté le plus récent dont le `LISEZMOI.txt` montre le bon nombre d'élèves.

- **Serveur sans WAMP (SQLite) :**
  1. Fermez la fenêtre de `demarrer.bat`.
  2. Renommez `database\database.sqlite` en `database.sqlite.ancien` (ne le supprimez pas).
  3. Copiez `database.sqlite` de la clé dans le dossier `database\`.
  4. Relancez `demarrer.bat`.
- **WAMP (MySQL) :** dans phpMyAdmin, ouvrez la base de l'application, onglet **Importer**, choisissez `base.sql`, puis **Exécuter**. Faites d'abord un export de la base actuelle par précaution.
- **Photos et documents :** copiez le contenu de `storage-app\` dans `storage\app\` de l'application.

Après une restauration, lancez `php artisan optimize:clear`.

## Applications téléphone (.apk) et Windows (.exe)

Le dossier `application/` contient une petite application qui demande **une seule fois l'adresse du serveur**, puis ouvre l'application de l'école.

- Les fichiers sont fabriqués automatiquement par GitHub. Ouvrez l'onglet **Actions**, puis le workflow « Applications Android et Windows », puis la page **Releases** :
  - **`SaintMartin.apk`** pour les téléphones Android : autorisez « installer des applications inconnues » ;
  - **`SaintMartin-Installation-x.y.z.exe`** pour Windows.
- Au premier lancement, tapez l'adresse affichée par `demarrer.bat`, par exemple `192.168.1.20:8000`. Si l'application est hébergée en ligne, tapez son adresse internet.
- Pour changer d'adresse, plusieurs moyens existent :
  - sous Windows, menu caché (touche Alt) : **Application** puis **Changer l'adresse du serveur** ;
  - sous Android, bouton « Changer d'adresse » sur l'écran de connexion ou sur la page « Serveur injoignable ».
- Pour imprimer les bulletins, reçus et cartes, utilisez le PC (application Windows avec Ctrl+P, ou navigateur). Sur Android, l'impression et certains téléchargements ne sont pas disponibles dans l'application.

## Mode hors ligne : appel, notes, encaissement

L'application peut faire l'appel, saisir des notes et encaisser **sans réseau**. Les saisies sont gardées sur le téléphone ou le PC, puis envoyées au serveur dès que le réseau revient.

1. **Une fois, avec le Wi-Fi de l'école :** ouvrez l'application et touchez **Travailler hors ligne**. Ce bouton est sur l'écran d'accueil et sur la page « Serveur injoignable ». Sous Windows, il est aussi dans le menu Application.
2. Connectez l'appareil avec votre e-mail et votre mot de passe. Les classes et les élèves sont alors téléchargés. Seuls ceux auxquels votre compte a droit le sont : un enseignant ne voit que ses cours et ses matières, un comptable que son cycle.
3. **Sans réseau**, travaillez normalement dans les onglets :
   - **Appel** : choisissez la classe, la date et le cours, puis cochez P, A ou R pour chaque élève ;
   - **Notes** : choisissez la classe, la matière et le trimestre, puis saisissez les notes des devoirs et de la composition. Les devoirs eux-mêmes (libellé, pourcentage) se créent sur le PC ;
   - **Encaissement** : cherchez l'élève. L'écran affiche le reste à payer. Un **reçu provisoire** (PROV-0001…) est donné tout de suite.
4. **Au retour du réseau**, les saisies partent toutes seules, ou avec le bouton **Envoyer**.
   - L'onglet **Envois** indique, pour chaque saisie, si elle a été enregistrée ou refusée, avec la raison.
   - Pour un encaissement, il donne aussi le **vrai numéro de reçu** (CS-…). Le reçu officiel s'imprime depuis le PC.
5. Touchez **Mettre à jour les données** de temps en temps, avec le réseau, pour récupérer les nouveaux élèves et les derniers paiements.

Chaque saisie passe par les mêmes contrôles que sur le PC : droits du compte, cycle, cours de l'enseignant, notes de 0 à 20. Une saisie envoyée deux fois, par exemple après une coupure pendant l'envoi, n'est enregistrée qu'une fois.

**Téléphone perdu, ou personne qui quitte l'école :** la Direction ouvre le menu **Système > Appareils connectés** et clique sur **Déconnecter**.

### Mises à jour de l'APK sans désinstaller (facultatif)

Ajoutez deux secrets dans GitHub (Settings, puis Secrets and variables, puis Actions) :

- `ANDROID_KEYSTORE_BASE64` : le fichier de clé `.jks` encodé en base64, avec l'alias `saintmartin` ;
- `ANDROID_KEYSTORE_PASSWORD` : son mot de passe.

Sans ces secrets, l'APK est une version de test. Chaque nouvelle version demande alors de désinstaller l'ancienne.

Pour publier une nouvelle version, changez `version` dans `application/package.json` (par exemple `1.0.1`).

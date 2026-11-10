# Héberger l'application sur Railway (pas à pas)

Résultat : l'application est accessible depuis n'importe quel téléphone ou ordinateur à une adresse `https://…up.railway.app` (ou votre propre nom de domaine). Les données sont conservées sur un **volume** Railway.

> Avertissement honnête : le `Dockerfile` n'a pas pu être testé en conditions réelles avant publication. Au premier déploiement, regardez l'onglet **Deploy Logs** ; si une erreur apparaît, envoyez-la-moi, c'est en général une correction d'une ligne.

## 1. Préparer
1. Un compte sur https://railway.com (connexion avec GitHub). Plan **Hobby** (environ 5 $/mois, crédit de 5 $ inclus) : le plan gratuit n'offre pas de volume durable adapté. Une carte bancaire internationale est nécessaire.
2. Générer une `APP_KEY` sur votre ordinateur : `php artisan key:generate --show` (ou, sans PHP : https://generate-random.org, 32 octets en base64, préfixé par `base64:`).

## 2. Créer le projet
1. Railway → **New Project** → **Deploy from GitHub repo** → choisissez `saint-martin-de-tour-toukra`.
2. Railway détecte le `Dockerfile` et `railway.json` tout seul. Le premier déploiement échouera tant que `APP_KEY` n'existe pas : c'est normal.

## 3. Volume (indispensable)
Service → **Settings → Volumes → Add Volume** → *Mount path* : `/data`.
Sans volume, **tout est perdu à chaque redéploiement** (base, photos, logo).

## 4. Variables
Service → **Variables → Raw Editor** : collez le contenu de `.env.railway.example` et remplacez :
- `APP_KEY` (étape 1), `APP_URL` (adresse du domaine, étape 5),
- `ECOLE_ADMIN_EMAIL` / `ECOLE_ADMIN_MOT_DE_PASSE` (10 caractères minimum) : ce sera le premier compte Direction, créé **une seule fois**.
Les comptes de démonstration (mot de passe `MotDePasse123`) ne sont **jamais** créés en ligne.

## 5. Adresse
Service → **Settings → Networking → Generate Domain**. Copiez l'adresse dans `APP_URL`, redéployez.

## 6. Première connexion
Ouvrez l'adresse, connectez-vous avec l'e-mail et le mot de passe choisis. Puis :
1. **Paramètres** : nom de l'école, logo, année scolaire.
2. **Licence** : collez la clé d'activation reçue (sinon la période d'essai de 30 jours s'applique).
3. Créez les comptes du personnel (menu Utilisateurs) et retirez `ECOLE_ADMIN_MOT_DE_PASSE` des variables.

## 7. Sauvegardes (à ne pas négliger)
Le volume n'est pas une sauvegarde : si le service est supprimé, il disparaît.
- Menu **Sauvegarde** (Direction) → *Télécharger* : un fichier ZIP contenant la base, les photos et le logo. **Faites-le chaque semaine** et gardez-le sur une clé USB / Google Drive de l'école. Cette page reste accessible même si la licence est en lecture seule.
- Railway propose aussi des sauvegardes de volume (Service → Volume → Backups), selon votre plan.

## 8. Application mobile (APK) et exécutable
Dans l'application, à la demande de l'adresse du serveur, saisissez `https://votre-domaine.up.railway.app`.

## 9. Mises à jour
Chaque `git push` sur la branche principale redéploie automatiquement. Les migrations s'exécutent seules au démarrage. Une coupure de quelques secondes est normale (un volume = pas de réplicas).

## 10. Restaurer des données existantes
1. Décompressez la sauvegarde ZIP.
2. Installez la CLI Railway (`npm i -g @railway/cli`), `railway login`, `railway link`.
3. Copiez dans le volume (via `railway ssh` du service) :
   - `database.sqlite` → `/data/database.sqlite` (service arrêté ou juste après un déploiement),
   - `storage-app/` → `/data/private/`,
   - `images-ecole/` → `/data/images-ecole/`.
   Exemple : `railway ssh` puis, depuis votre machine, `scp` selon la documentation https://docs.railway.com/cli/ssh.
4. Redéployez. Si vous restaurez une ancienne base, les migrations manquantes s'appliquent automatiquement.

## Fuseau horaire et coûts
`APP_TIMEZONE=Africa/Ndjamena` règle l'heure affichée. Coût typique pour une école : quelques dollars par mois, à surveiller dans **Usage**.

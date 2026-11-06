# Gestion scolaire : Complexe Scolaire Saint Martin de Tour de Toukra

Application Laravel de gestion d'école (élèves, classes, notes, bulletins, présences, emplois du temps,
scolarité et reçus, personnel et paie, comptabilité, logistique, cartes de retrait, discipline,
cahier de texte, espace parents), avec applications Windows et Android.

Cette version est dérivée de l'application de gestion scolaire de la Cité du Savoir, **sans** les volets :

- cours en ligne (leçons, devoirs, classes virtuelles, quiz) et comptes élèves ;
- infirmerie ;
- bibliothèque (livres et emprunts).

Installation, sauvegarde, restauration et applications : voir [INSTALLATION.md](INSTALLATION.md).

## Développement

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed --class=StructureEcoleSeeder
php artisan test
```

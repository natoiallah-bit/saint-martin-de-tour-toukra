#!/bin/sh
# Démarrage du conteneur : branche le volume persistant, prépare la base, lance Apache.
set -e
cd /var/www/html

PORT="${PORT:-8080}"
export PORT
sed -ri "s/Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/Listen 443\$/Listen 0.0.0.0:8443/" /etc/apache2/ports.conf 2>/dev/null || true

DATA="${DATA_DIR:-/data}"
if [ -d "$DATA" ]; then
    mkdir -p "$DATA/private" "$DATA/images-ecole"
    # Fichiers privés de l'école (photos, documents, imports)
    rm -rf storage/app/private
    ln -s "$DATA/private" storage/app/private
    # Logo, cachet, signature
    rm -rf public/images/ecole
    ln -s "$DATA/images-ecole" public/images/ecole
    # Base SQLite sur le volume
    if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
        export DB_DATABASE="${DB_DATABASE:-$DATA/database.sqlite}"
        [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
    fi
    chown -R www-data:www-data "$DATA"
fi
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ -z "$APP_KEY" ]; then
    echo "ERREUR : la variable APP_KEY est absente. Voir docs/HEBERGEMENT-RAILWAY.md" >&2
    exit 1
fi

# Les commandes artisan s'exécutent en tant que www-data pour que les fichiers lui appartiennent
run() { su -s /bin/sh www-data -c "cd /var/www/html && $*"; }

run "php artisan migrate --force"
run "php artisan db:seed --class=StructureEcoleSeeder --force" || true
run "php artisan ecole:premier-compte" || true
run "php artisan config:cache && php artisan route:cache && php artisan view:cache" || true

exec "$@"

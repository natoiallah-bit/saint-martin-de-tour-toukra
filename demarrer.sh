#!/usr/bin/env sh
# Lance le serveur sur Linux / Mac (sur Windows : demarrer.bat)
cd "$(dirname "$0")"
php artisan migrate --force >/dev/null
php artisan ecole:adresses --port=8000
echo "  Laissez ce terminal ouvert pendant que l'ecole travaille."
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8000 --no-reload

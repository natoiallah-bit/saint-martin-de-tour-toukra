# Image de production pour Railway (ou tout hébergeur Docker)
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libonig-dev libxml2-dev \
        libsodium-dev sqlite3 libsqlite3-dev default-mysql-client unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip intl mbstring pdo_mysql pdo_sqlite bcmath exif opcache sodium \
    && a2enmod rewrite headers remoteip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi || true

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ecole.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/images

EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

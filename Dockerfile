# PHP 8.2 ke saath Apache use karenge
FROM php:8.2-apache

# PostgreSQL extension ke liye zaruri dependencies
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Aapki files ko web directory mein copy karein
COPY . /var/www/html/

# Permissions set karein
RUN chown -R www-data:www-data /var/www/html

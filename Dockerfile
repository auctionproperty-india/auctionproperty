FROM php:8.2-apache

# PostgreSQL extension ke liye dependencies aur driver install karna
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && docker-php-ext-enable pdo_pgsql

# Aapki files ko web server mein copy karna
COPY . /var/www/html/

# Permissions sahi karna
RUN chown -R www-data:www-data /var/www/html

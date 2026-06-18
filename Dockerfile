FROM php:8.2-apache

# PostgreSQL extension aur SSL dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Web dir setup
WORKDIR /var/www/html
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

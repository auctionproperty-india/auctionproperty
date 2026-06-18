FROM php:8.2-apache

# libssl-dev ko explicitly add kiya hai
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libssl-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

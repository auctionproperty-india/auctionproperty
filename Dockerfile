FROM php:8.2-apache

# PostgreSQL support ke liye zaroori libraries aur drivers install karna
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Web directory set karna
WORKDIR /var/www/html
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html

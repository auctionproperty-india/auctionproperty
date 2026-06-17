FROM php:8.2-apache

# Install PostgreSQL libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set directory
WORKDIR /var/www/html
COPY . /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

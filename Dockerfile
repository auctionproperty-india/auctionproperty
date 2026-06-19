FROM php:8.2-apache

# Install PostgreSQL, GD, and required libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_pgsql

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy code
COPY . /var/www/html/

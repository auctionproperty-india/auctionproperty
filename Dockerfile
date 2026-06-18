FROM php:8.2-apache

# Install required dependencies for SSL and PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libssl-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Fix directory permissions
RUN chown -R www-data:www-data /var/www/html

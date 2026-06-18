# PHP 8.2 with Apache
FROM php:8.2-apache

# PostgreSQL Extension Install करें (Render के Database के लिए जरूरी)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql

# Apache Rewrite Module Enable करें
RUN a2enmod rewrite

# सारी PHP Files Web Root में Copy करें
COPY . /var/www/html/

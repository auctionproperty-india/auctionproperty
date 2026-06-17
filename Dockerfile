FROM php:8.2-apache

# आवश्यक टूल्स और PostgreSQL ड्राइवर इंस्टॉल करना
RUN apt-get update && apt-get install -y libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# कंपोज़र (Composer) इंस्टॉल करना - जो PHPMailer को लाएगा
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html/

# PHPMailer इंस्टॉल करना
RUN composer require phpmailer/phpmailer

EXPOSE 80

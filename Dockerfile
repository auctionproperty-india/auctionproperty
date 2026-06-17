FROM php:8.2-apache

# PostgreSQL ड्राइवर इंस्टॉल करने के लिए ये कमांड्स ज़रूरी हैं
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# आपके प्रोजेक्ट की फाइलों को सर्वर में कॉपी करने के लिए
COPY . /var/www/html/

# पोर्ट 80 को ओपन करने के लिए
EXPOSE 80

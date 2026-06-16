
FROM php:8.2-apache
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# ज़िप फाइल को डाउनलोड और अनज़िप करने के लिए जरूरी टूल्स
RUN apt-get update && apt-get install -y unzip wget

# रेंडर सर्वर के फोल्डर में जाना
WORKDIR /var/www/html/

# गूगल ड्राइव से 1.5 GB की ज़िप फाइल सीधे सर्वर पर डाउनलोड करना
RUN wget --no-check-certificate "https://docs.google.com/uc?export=download&id=1_ZOac7xr32IzqO1J5SMIMC1dRILQHpis" -O software.zip && \
    unzip -o software.zip && \
    rm software.zip

EXPOSE 80

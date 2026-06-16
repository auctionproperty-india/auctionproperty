
FROM php:8.2-apache
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# जरूरी टूल्स इंस्टॉल करना
RUN apt-get update && apt-get install -y unzip wget curl

WORKDIR /var/www/html/

# बड़ी गूगल ड्राइव फाइल को वायरस स्कैन वार्निंग बाईपास करके डाउनलोड करने का पक्का तरीका
RUN curl -Lb /tmp/cookies.txt "https://docs.google.com/uc?export=download&confirm=$(curl -sL -b /tmp/cookies.txt 'https://docs.google.com/uc?export=download&id=1_ZOac7xr32IzqO1J5SMIMC1dRILQHpis' | grep -o 'confirm=[^&]*' | sed 's/confirm=//')&id=1_ZOac7xr32IzqO1J5SMIMC1dRILQHpis" -o software.zip && \
    unzip -o software.zip && \
    rm software.zip

EXPOSE 80

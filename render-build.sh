#!/usr/bin/env bash
# Install PHP extensions
apt-get update && apt-get install -y php-pgsql php-pdo-pgsql
# Configure Apache to look at the project folder
sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

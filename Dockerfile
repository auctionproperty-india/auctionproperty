# ============================================================
# 🐳 Dockerfile for PHP with PostgreSQL on Render
# ============================================================

# Use official PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies and PostgreSQL extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Enable Apache mod_rewrite (for clean URLs)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all application files
COPY . /var/www/html/

# ============================================================
# ✅ DATABASE ENVIRONMENT VARIABLES (Supabase Connection)
# ============================================================
ENV DB_HOST=aws-0-ap-northeast-2.pooler.supabase.com
ENV DB_PORT=6543
ENV DB_NAME=postgres
ENV DB_USER=postgres.bqspzgwpqimjyhispwtp
ENV DB_PASSWORD=Dugguai@123
ENV APP_ENV=production

# ============================================================
# ✅ Optional: Configure Apache to serve from public directory
# ============================================================
# Agar aapka code "public" folder mein hai toh yeh uncomment karein
# RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Set permissions (Render ke liye required)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80 (Render automatically maps it)
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

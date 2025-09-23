# Use official PHP image with Apache
FROM php:8.2-apache

# Install extensions (mysqli, pdo_mysql for DB support)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project files into container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose Apache port
EXPOSE 80

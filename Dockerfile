# Use official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies for PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev

# Install PHP extensions (MySQL + PostgreSQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql pgsql pdo_pgsql

# Copy project files into container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose Apache port
EXPOSE 80

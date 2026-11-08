FROM php:8.2-apache

# Install PDO MySQL extension and recommended utilities
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# Copy project files into container's web root
COPY . /var/www/html/

# Set appropriate permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80


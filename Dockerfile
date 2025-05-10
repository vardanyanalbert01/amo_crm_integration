# Use PHP with Apache
FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory to /var/www/html
WORKDIR /var/www/html

# Copy all project files into the container
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Use custom vhost config if available
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

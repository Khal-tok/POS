FROM php:8.2-apache
# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql
# Copy your code into the web server directory
COPY . /var/www/html/
# Expose port 80
EXPOSE 80
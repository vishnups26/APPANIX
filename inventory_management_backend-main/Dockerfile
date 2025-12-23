FROM php:8.1-apache

# Copy application files
WORKDIR /var/www/html
COPY src/ ./
COPY db/ ./db/

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]

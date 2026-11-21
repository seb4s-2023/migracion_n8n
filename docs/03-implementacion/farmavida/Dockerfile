FROM php:8.2-apache

# mysqli lo usa config.php; mbstring, curl y fileinfo ya vienen en la imagen
RUN docker-php-ext-install mysqli

# Zona horaria de Colombia
RUN echo "date.timezone=America/Bogota" > /usr/local/etc/php/conf.d/tz.conf

# Permite el .htaccess del proyecto
RUN a2enmod rewrite && \
    sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/

# Railway asigna el puerto en la variable PORT: Apache debe escuchar ahí
CMD ["sh", "-c", "sed -i \"s/80/${PORT:-80}/g\" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf && apache2-foreground"]

RUN mkdir -p /var/www/html/storage/formulas && chown -R www-data:www-data /var/www/html/storage

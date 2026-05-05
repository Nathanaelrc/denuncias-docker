# ====================================================
# Portal de Denuncias Ciudadanas Empresa Portuaria Coquimbo - Dockerfile
# PHP 8.5 con Apache y extensiones necesarias
# ====================================================
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    libxml2-dev libonig-dev libsodium-dev unzip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli mbstring xml zip gd bcmath sodium \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN echo '<Directory /var/www/html/public>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/generales.conf \
    && a2enconf generales

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/generales.ini

RUN mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/public/uploads/evidencia \
    && chown -R www-data:www-data /var/www/html

COPY --chown=www-data:www-data . /var/www/html/

# Instalar dependencias PHP via Composer (PHPMailer y otras)
RUN cd /var/www/html && composer install --no-dev --no-interaction --optimize-autoloader --no-progress 2>/dev/null || true

RUN chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/logs \
    && chmod -R 775 /var/www/html/public/uploads

COPY --chown=root:root docker/app-entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN chmod +x /usr/local/bin/app-entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://localhost/salud || exit 1

ENTRYPOINT ["app-entrypoint.sh"]
CMD ["apache2-foreground"]

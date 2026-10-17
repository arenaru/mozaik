FROM php:8.3-apache

# GD with JPEG/PNG/WebP support (needed to re-encode uploads) + PDO MySQL.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# PHP hardening
COPY docker/php/security.ini /usr/local/etc/php/conf.d/zz-security.ini

# Apache hardening + vhost
COPY docker/apache/security.conf /etc/apache2/conf-available/zz-security.conf
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod headers \
    && a2enconf zz-security \
    && a2dismod -f autoindex status

COPY docker/app/entrypoint.sh /usr/local/bin/mozaic-entrypoint
RUN chmod 0755 /usr/local/bin/mozaic-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["mozaic-entrypoint"]
CMD ["apache2-foreground"]

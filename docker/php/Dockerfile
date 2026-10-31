FROM php:8.5-apache

# intl: French date formatting. pdo_mysql: comments storage. curl is compiled into the official image.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev unzip git curl \
    && docker-php-ext-install intl pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Serve only public/: src/, tests/ and .env are never reachable over HTTP.
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache/zz-app.conf /etc/apache2/conf-enabled/zz-app.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

# `docker compose exec app composer ...` runs as root: allowed in this dev container only.
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]

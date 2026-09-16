FROM dunglas/frankenphp:1-php8.5-alpine

RUN install-php-extensions pdo_sqlite intl opcache zip \
    && apk add --no-cache sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:80 \
    DATABASE_URL="sqlite:////var/lib/chantier/chantier.db"

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && php bin/console cache:warmup \
    && php bin/console assets:install public \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    && mkdir -p /var/lib/chantier var \
    && chown -R www-data:www-data /var/lib/chantier var

COPY docker/entrypoint.sh /usr/local/bin/chantier-entrypoint
RUN chmod +x /usr/local/bin/chantier-entrypoint

VOLUME /var/lib/chantier
EXPOSE 80

ENTRYPOINT ["chantier-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

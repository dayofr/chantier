# syntax=docker/dockerfile:1

# Versions figées en clair dans les FROM : Dependabot propose les mises à jour.
# Garder les deux images FrankenPHP à la même version.

FROM composer:2.10.3 AS composer

# ---------------------------------------------------------------------------
# Construction : dépendances, cache Symfony, CSS Tailwind, assets compilés.
# Tourne sur la plateforme de la machine de build : le résultat (PHP, CSS) ne
# dépend pas de l'architecture, inutile de l'émuler pour arm64.
# ---------------------------------------------------------------------------
FROM --platform=$BUILDPLATFORM dunglas/frankenphp:1.12.7-php8.5.10-alpine AS build

RUN install-php-extensions intl pdo_sqlite zip
COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer dump-env prod \
    && php bin/console cache:warmup \
    && php bin/console assets:install public \
    # Bibliothèques JS de l'importmap (assets/vendor, non versionné).
    && php bin/console importmap:install \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    # Le binaire Tailwind (~100 Mo) ne sert qu'à la construction.
    && rm -rf var/tailwind var/log \
    # Écrit à l'exécution ; ouvert à tous pour permettre --user avec un autre uid (NAS).
    && chmod -R a+rwX var

# ---------------------------------------------------------------------------
# Image finale : PHP de production, sans outils de construction, non root.
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1.12.7-php8.5.10-alpine

ARG VERSION=dev
LABEL org.opencontainers.image.title="Chantier" \
      org.opencontainers.image.description="Mini Jira pour suivre le travail de Claude Code : serveur MCP, API REST et interface en lecture seule." \
      org.opencontainers.image.licenses="BSD-3-Clause" \
      org.opencontainers.image.version="${VERSION}"

RUN install-php-extensions intl opcache pdo_sqlite \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    # sqlite3 : sauvegardes à chaud (.backup) ; su-exec : abandon des droits root au démarrage.
    && apk add --no-cache sqlite su-exec

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-chantier.ini"

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_TIMEZONE=Europe/Paris \
    DATABASE_URL="sqlite:////var/lib/chantier/chantier.db" \
    # Port non privilégié : pas besoin de root pour écouter.
    SERVER_NAME=:8080

WORKDIR /app

COPY --from=build --chown=www-data:www-data /app /app
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/chantier-entrypoint

# Données et état de Caddy, écrits à l'exécution. Ouverts à tous pour permettre --user (NAS).
RUN mkdir -p /var/lib/chantier \
    && chown www-data:www-data /var/lib/chantier \
    && chmod -R a+rwX /data /config

VOLUME /var/lib/chantier
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1:8080/_live/version || exit 1

ENTRYPOINT ["chantier-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

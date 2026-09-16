#!/bin/sh
set -e

# Crée ou met à jour la base SQLite avant de servir.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
chown -R www-data:www-data /var/lib/chantier var

exec docker-php-entrypoint "$@"

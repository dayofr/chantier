#!/bin/sh
set -e

# Crée ou met à jour la base SQLite avant de servir.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Sur un dossier monté depuis l'hôte (NAS), changer le propriétaire peut être refusé : sans gravité,
# le serveur tourne en root dans le conteneur.
chown -R www-data:www-data var 2>/dev/null || true
chown -R www-data:www-data /var/lib/chantier 2>/dev/null || echo "Note : propriétaire de /var/lib/chantier inchangé (dossier de l'hôte)."

exec docker-php-entrypoint "$@"

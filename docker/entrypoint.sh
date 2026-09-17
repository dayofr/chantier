#!/bin/sh
set -e

data_dir=/var/lib/chantier

# Pas d'authentification ni de session : un secret aléatoire par démarrage suffit s'il n'est pas fourni.
if [ -z "${APP_SECRET:-}" ]; then
    APP_SECRET=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
    export APP_SECRET
fi

# Démarré en root (cas par défaut) : on rend les données à www-data puis on abandonne les droits.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data "$data_dir" /app/var 2>/dev/null \
        || echo "Avertissement : propriétaire de $data_dir inchangé (dossier de l'hôte ?)." >&2
    exec su-exec www-data "$0" "$@"
fi

# Essai d'écriture réel : sur un dossier monté depuis l'hôte, les permissions affichées
# ne reflètent pas toujours les droits effectifs.
probe="$data_dir/.write-test-$$"
if ! : > "$probe" 2>/dev/null; then
    echo "Erreur : $data_dir n'est pas accessible en écriture pour l'uid $(id -u)." >&2
    echo "Donner ce dossier à cet uid sur l'hôte, ou lancer le conteneur avec l'uid propriétaire du dossier" >&2
    echo "(docker run --user <uid>:<gid>, ou CHANTIER_UID / CHANTIER_GID avec compose.nas.yaml)." >&2
    exit 1
fi
rm -f "$probe"

# Crée ou met à jour la base SQLite avant de servir.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec docker-php-entrypoint "$@"

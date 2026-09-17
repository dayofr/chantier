#!/bin/sh
# Hook Claude Code "Stop" pour Chantier.
#
# Quand la conversation a nettement avancé depuis le dernier rappel, empêche Claude de s'arrêter
# et lui demande d'appeler save_session_summary. Ne fait rien si la session n'utilise pas Chantier.
#
# Réglages (variables d'environnement) :
#   CHANTIER_MCP_SERVER            nom du serveur MCP dans Claude Code (défaut : chantier)
#   CHANTIER_SUMMARY_EVERY_BYTES   croissance du transcript entre deux rappels (défaut : 60000)
#
# Sortie 2 + message sur stderr : Claude reprend la main avec ce message.

server="${CHANTIER_MCP_SERVER:-chantier}"
every="${CHANTIER_SUMMARY_EVERY_BYTES:-60000}"

input=$(cat | tr -d '\n')

# Déjà relancé par un hook Stop : laisser s'arrêter, sinon boucle.
case "$input" in
    *'"stop_hook_active":true'* | *'"stop_hook_active": true'*) exit 0 ;;
esac

json_field() {
    # Valeur texte simple ; les "\/" autorisés par JSON redeviennent "/".
    printf '%s' "$input" | sed -n "s/.*\"$1\"[[:space:]]*:[[:space:]]*\"\([^\"]*\)\".*/\1/p" | sed 's#\\/#/#g'
}

transcript=$(json_field transcript_path)
session=$(json_field session_id)
[ -n "$transcript" ] && [ -f "$transcript" ] && [ -n "$session" ] || exit 0

# Session qui n'utilise pas Chantier : rien à résumer.
grep -q "mcp__${server}__" "$transcript" || exit 0

state_dir="${TMPDIR:-/tmp}/chantier-hooks"
mkdir -p "$state_dir" 2>/dev/null || exit 0
state="$state_dir/$(printf '%s' "$session" | tr -c 'A-Za-z0-9_-' '_')"

size=$(wc -c < "$transcript" | tr -d ' ')
last=$(cat "$state" 2>/dev/null || echo 0)
case "$last" in *[!0-9]* | '') last=0 ;; esac

[ $((size - last)) -ge "$every" ] || exit 0
printf '%s' "$size" > "$state"

# Résumé déjà envoyé depuis le dernier rappel : pas besoin de redemander.
if tail -c +"$((last + 1))" "$transcript" | grep -q "mcp__${server}__save_session_summary"; then
    exit 0
fi

cat >&2 <<MSG
Chantier : la conversation a avancé depuis le dernier résumé de séance.
Avant de t'arrêter, appelle l'outil ${server} save_session_summary avec :
- summary : résumé complet de la séance (demandes de l'utilisateur, travail fait, points ouverts), pas seulement la dernière étape ;
- decisions : les décisions prises depuis le début, avec le ticket concerné quand il existe.
Si start_session n'a pas encore été appelé, appelle-le d'abord avec l'objectif de la séance.
Ensuite, termine normalement sans autre commentaire.
MSG
exit 2

#!/bin/sh
# Hook Claude Code "SessionStart" pour Chantier : rappelle le suivi au début d'une séance.
# Le texte écrit sur stdout est ajouté au contexte de Claude.

server="${CHANTIER_MCP_SERVER:-chantier}"
input=$(cat | tr -d '\n')
source=$(printf '%s' "$input" | sed -n 's/.*"source"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')

case "$source" in
    compact)
        cat <<MSG
Chantier : le contexte vient d'être compacté. Dès que possible, appelle ${server} save_session_summary
avec un résumé complet de la séance reconstitué à partir du résumé de compaction, et les décisions connues.
MSG
        ;;
    *)
        cat <<MSG
Chantier : ce projet est suivi dans Chantier (serveur MCP ${server}).
Dès que l'objectif de la séance est clair, appelle ${server} start_session avec un titre court ;
il renvoie les résumés des séances précédentes.
MSG
        ;;
esac
exit 0

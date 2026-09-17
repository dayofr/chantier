# Hook Claude Code "SessionStart" pour Chantier, version PowerShell (Windows sans Git Bash).
# Même comportement que chantier-session-start.sh : le texte écrit sur stdout est ajouté au contexte de Claude.
# Compatible Windows PowerShell 5.1 et PowerShell 7. Fichier en UTF-8 avec BOM pour 5.1.

try {
    [Console]::InputEncoding = [Text.Encoding]::UTF8
    [Console]::OutputEncoding = [Text.Encoding]::UTF8
} catch {}
trap { exit 0 }

$server = if ($env:CHANTIER_MCP_SERVER) { $env:CHANTIER_MCP_SERVER } else { 'chantier' }
$source = ''
try { $source = [string]([Console]::In.ReadToEnd() | ConvertFrom-Json).source } catch {}

if ($source -eq 'compact') {
    [Console]::Out.WriteLine(@"
Chantier : le contexte vient d'être compacté. Dès que possible, appelle $server save_session_summary
avec un résumé complet de la séance reconstitué à partir du résumé de compaction, et les décisions connues.
"@)
} else {
    [Console]::Out.WriteLine(@"
Chantier : ce projet est suivi dans Chantier (serveur MCP $server).
Dès que l'objectif de la séance est clair, appelle $server start_session avec un titre court ;
il renvoie les résumés des séances précédentes.
"@)
}
exit 0

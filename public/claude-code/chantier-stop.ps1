# Hook Claude Code "Stop" pour Chantier, version PowerShell (Windows sans Git Bash).
# Même comportement que chantier-stop.sh.
#
# Quand la conversation a nettement avancé depuis le dernier rappel, empêche Claude de s'arrêter
# et lui demande d'appeler save_session_summary. Ne fait rien si la session n'utilise pas Chantier.
#
# Réglages (variables d'environnement) :
#   CHANTIER_MCP_SERVER            nom du serveur MCP dans Claude Code (défaut : chantier)
#   CHANTIER_SUMMARY_EVERY_BYTES   croissance du transcript entre deux rappels (défaut : 60000)
#
# Sortie 2 + message sur stderr : Claude reprend la main avec ce message.
# Compatible Windows PowerShell 5.1 et PowerShell 7. Fichier en UTF-8 avec BOM pour 5.1.

$ErrorActionPreference = 'Stop'
try {
    [Console]::InputEncoding = [Text.Encoding]::UTF8
    [Console]::OutputEncoding = [Text.Encoding]::UTF8
} catch {}

# Toute erreur inattendue laisse Claude s'arrêter normalement.
trap { exit 0 }

$server = if ($env:CHANTIER_MCP_SERVER) { $env:CHANTIER_MCP_SERVER } else { 'chantier' }
$every = 60000
if ($env:CHANTIER_SUMMARY_EVERY_BYTES -match '^\d+$') { $every = [long]$env:CHANTIER_SUMMARY_EVERY_BYTES }

$hookInput = [Console]::In.ReadToEnd() | ConvertFrom-Json

# Déjà relancé par un hook Stop : laisser s'arrêter, sinon boucle.
if ($hookInput.stop_hook_active -eq $true) { exit 0 }

$transcript = [string]$hookInput.transcript_path
$session = [string]$hookInput.session_id
if (-not $transcript -or -not $session -or -not (Test-Path -LiteralPath $transcript -PathType Leaf)) { exit 0 }

function Read-From([string]$path, [long]$offset) {
    # Le transcript est en cours d'écriture par Claude Code : lecture partagée.
    $stream = [IO.File]::Open($path, [IO.FileMode]::Open, [IO.FileAccess]::Read, [IO.FileShare]::ReadWrite)
    try {
        [void]$stream.Seek($offset, [IO.SeekOrigin]::Begin)
        $reader = New-Object IO.StreamReader($stream, [Text.Encoding]::UTF8)
        return $reader.ReadToEnd()
    } finally {
        $stream.Dispose()
    }
}

$size = (Get-Item -LiteralPath $transcript).Length

# Session qui n'utilise pas Chantier : rien à résumer.
if (-not (Read-From $transcript 0).Contains("mcp__${server}__")) { exit 0 }

$stateDir = Join-Path ([IO.Path]::GetTempPath()) 'chantier-hooks'
New-Item -ItemType Directory -Force -Path $stateDir | Out-Null
$state = Join-Path $stateDir ($session -replace '[^A-Za-z0-9_-]', '_')

$last = 0
if (Test-Path -LiteralPath $state) {
    $stored = (Get-Content -LiteralPath $state -Raw).Trim()
    if ($stored -match '^\d+$') { $last = [long]$stored }
}

if ($size - $last -lt $every) { exit 0 }
Set-Content -LiteralPath $state -Value $size -NoNewline

# Résumé déjà envoyé depuis le dernier rappel : pas besoin de redemander.
if ($last -le $size -and (Read-From $transcript $last).Contains("mcp__${server}__save_session_summary")) { exit 0 }

[Console]::Error.WriteLine(@"
Chantier : la conversation a avancé depuis le dernier résumé de séance.
Avant de t'arrêter, appelle l'outil $server save_session_summary avec :
- summary : résumé complet de la séance (demandes de l'utilisateur, travail fait, points ouverts), pas seulement la dernière étape ;
- decisions : les décisions prises depuis le début, avec le ticket concerné quand il existe.
Si start_session n'a pas encore été appelé, appelle-le d'abord avec l'objectif de la séance.
Ensuite, termine normalement sans autre commentaire.
"@)
exit 2

# Brancher Claude Code sur Chantier

Trois niveaux, du plus simple au plus fiable. Remplacer `chantier.local:8080` par l'adresse du serveur.

## 1. Serveur MCP

```bash
claude mcp add --transport http --scope user chantier http://chantier.local:8080/mcp
```

Le serveur envoie déjà des instructions à Claude : `start_session` en début de séance,
`save_session_summary` après chaque étape importante et en fin de séance.

### Séances

Claude Code utilise la révision MCP `2026-07-28`, sans session de connexion. Chantier regroupe donc les appels en séances ainsi :

- les appels d'un même client (ex. `claude-code`) sont rattachés à sa dernière séance active ;
- `start_session` ouvre une nouvelle séance, que les appels suivants rejoignent ;
- après `APP_SESSION_IDLE_HOURS` heures sans écriture (4 par défaut), une nouvelle séance s'ouvre ;
- plusieurs Claude Code en parallèle : chacun passe l'id renvoyé par `start_session` dans le paramètre `session` des outils qui écrivent.

Les outils de lecture n'ouvrent pas de séance.

## 2. Consignes dans le CLAUDE.md du projet suivi

```markdown
## Suivi de projet
Ce projet est suivi dans Chantier (MCP `chantier`, clé projet `XXX`).
- Début de séance : `start_session` avec l'objectif, puis `get_next_ticket`.
- Si plusieurs agents travaillent en parallèle : passer `session` (id renvoyé par `start_session`) aux outils qui écrivent.
- Avant de coder : passer le ticket en `in_progress`. Pas de travail hors ticket : sinon `create_tickets`.
- Pendant : `log_activity` pour décisions, blocages, commits, résultats de tests.
- Une décision qui vaut pour tout un epic ou une initiative : `log_activity` avec `subject` = sa clé (ex. `XXX-E3`).
- Fin : cocher les sous-tâches, `add_link` pour commits/PR, statut `in_review` ou `done`.
- Après chaque étape importante et en fin de séance : `save_session_summary` (résumé complet + décisions).
```

Consignes et instructions MCP ne garantissent pas le résumé : Claude peut l'oublier, surtout en fin de séance.

## 3. Hooks (recommandé pour le résumé)

Chantier ne voit pas la conversation. Deux hooks font écrire le résumé par Claude :

| Hook | Script | Effet |
|---|---|---|
| `Stop` | `chantier-stop.sh` | Quand la conversation a avancé d'environ 60 Ko depuis le dernier rappel, empêche Claude de s'arrêter et lui demande `save_session_summary`. Silencieux si la séance n'utilise pas Chantier ou si le résumé vient d'être envoyé. |
| `SessionStart` | `chantier-session-start.sh` | Au démarrage : rappelle `start_session`. Après compaction : demande de reconstituer le résumé. |

Pourquoi pas `PreCompact` : ce hook ne peut ni bloquer ni faire agir Claude. Le hook `Stop` limité en fréquence
garantit un résumé récent avant qu'une compaction n'efface les détails.

### Installation

```bash
mkdir -p ~/.claude/hooks
curl -fsSL http://chantier.local:8080/claude-code/chantier-stop.sh -o ~/.claude/hooks/chantier-stop.sh
curl -fsSL http://chantier.local:8080/claude-code/chantier-session-start.sh -o ~/.claude/hooks/chantier-session-start.sh
```

Windows (PowerShell) :

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\.claude\hooks" | Out-Null
Invoke-WebRequest http://chantier.local:8080/claude-code/chantier-stop.ps1 -OutFile "$env:USERPROFILE\.claude\hooks\chantier-stop.ps1"
Invoke-WebRequest http://chantier.local:8080/claude-code/chantier-session-start.ps1 -OutFile "$env:USERPROFILE\.claude\hooks\chantier-session-start.ps1"
```

Modèle de réglages Windows : `/claude-code/settings.windows.example.json`. Les commandes passent `-ExecutionPolicy Bypass`,
car un script téléchargé est bloqué par la stratégie d'exécution par défaut.

Puis fusionner la configuration dans les réglages Claude Code
([modèle](../public/claude-code/settings.example.json), aussi servi sur `/claude-code/settings.example.json`) :

- **`Stop`** : dans `~/.claude/settings.json` (tous les projets). Sans effet sur les séances qui n'utilisent pas Chantier.
- **`SessionStart`** : dans `.claude/settings.json` de chaque projet suivi, sinon le rappel apparaît partout.

Les scripts n'utilisent que `sh`, `sed`, `grep`, `tail` et `wc`.

| Système | Statut |
|---|---|
| macOS | Testé (`sh` de macOS) |
| Linux | Testé (`dash`, le `sh` de Debian/Ubuntu) |
| Windows avec Git Bash | Scripts `sh` : devraient fonctionner, Claude Code lance les hooks dans Git Bash. Non testé. Ou utiliser la version PowerShell ci-dessous. |
| Windows sans Git Bash | Scripts PowerShell `chantier-stop.ps1` et `chantier-session-start.ps1`. Testés avec PowerShell 7.4 ; écrits pour Windows PowerShell 5.1 mais non testés sur Windows. |
| WSL | Comme Linux, si Claude Code tourne dans WSL. |

Tests des scripts : `tests/ClaudeCode/StopHookTest.php` (version `sh`, dans la suite PHPUnit)
et `tests/ClaudeCode/powershell-hooks.ps1` (version PowerShell, commande en tête du fichier).

### Réglages

| Variable | Défaut | Rôle |
|---|---|---|
| `CHANTIER_MCP_SERVER` | `chantier` | Nom du serveur MCP dans Claude Code |
| `CHANTIER_SUMMARY_EVERY_BYTES` | `60000` | Croissance du transcript entre deux rappels |

L'état (taille du transcript au dernier rappel) est gardé dans `$TMPDIR/chantier-hooks/`.

### Limites connues

- Le seuil en octets est une approximation de « la conversation a avancé » : les longues sorties d'outils comptent autant que les échanges.
- Le résumé est demandé à la fin d'une réponse, jamais au milieu d'une tâche.
- Si Claude Code change le format d'entrée des hooks, les scripts laissent passer (sortie 0) plutôt que de bloquer.

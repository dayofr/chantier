# Chantier

[![CI](https://github.com/DayoFr/chantier/actions/workflows/ci.yml/badge.svg)](https://github.com/DayoFr/chantier/actions/workflows/ci.yml)
[![Image](https://img.shields.io/badge/image-ghcr.io%2Fdayofr%2Fchantier-blue)](https://github.com/dayofr/chantier/pkgs/container/chantier)
[![Licence](https://img.shields.io/badge/licence-BSD--3--Clause-green)](LICENSE)

Mini Jira for tracking Claude Code's work. The agent writes via MCP, humans read.

> **No authentication.** Chantier is designed for a trusted local network. Do not expose it to the Internet.

- **Web UI**: `/` (read-only, fr/en, light/dark theme)
- **REST API**: `/api` (Swagger docs at `/api/docs`)
- **MCP server**: `/mcp` (HTTP streamable)
- **Stack**: Symfony 8.1, API Platform 4.3, SQLite, Twig + Tailwind 4 (AssetMapper, no Node)

## Interface

| Page | URL |
|---|---|
| Portfolio | `/fr` |
| Project overview | `/fr/projects/CHANT` |
| Board (filter `?epic=CHANT-E4` or `?epic=none`) | `/fr/projects/CHANT/board` |
| Initiative: description, decisions, board (filter `?epic=`) | `/fr/initiatives/CHANT-I4` |
| Ticket detail | `/fr/tickets/CHANT-12` |
| Activity (filter `?session=…`) | `/fr/activity`, `/fr/projects/CHANT/activity` |

`/en/...` for English. Dates are displayed in the `APP_TIMEZONE` timezone (default `Europe/Paris`) and stored in UTC.
Fonts and icons are served locally (`assets/fonts`), so the UI works offline.
Icons are a subset: to use a new one, add it to `assets/fonts/icons.txt` then run `php bin/download-fonts`.

An initiative view shows its description, its decisions (made on the initiative, its epics or their tickets, filtered with the selected epic) and its tickets board.
An initiative or epic description sets the scope; decisions are recorded as dated entries attached to their session.

A project shows alerts: bottlenecks, in-progress tickets with no activity for `APP_STALE_HOURS` hours (48 by default), blocked tickets, tickets without epic.

## Model

```
Project (CHANT)
  └─ Initiative (CHANT-I1)
      └─ Epic (CHANT-E1)
          └─ Ticket (CHANT-12): subtasks, dependencies, links
Activity: log (automatic + agent notes)
```

Keys are used as identifiers everywhere: `/api/tickets/CHANT-12`, `get_ticket {"ticket": "CHANT-12"}`.

Ticket statuses: `backlog`, `todo`, `in_progress`, `in_review`, `blocked`, `done`, `cancelled`.
A ticket is also considered blocked when a ticket blocking it is not finished.

## Run

Multi-architecture image (amd64, arm64): `ghcr.io/dayofr/chantier`, tags `latest`, `0.2.0`, `0.2`…
Container package (tags, SBOM, provenance): https://github.com/dayofr/chantier/pkgs/container/chantier

### Docker

```bash
docker run -d --name chantier -p 8080:8080 -v chantier-data:/var/lib/chantier --restart unless-stopped ghcr.io/dayofr/chantier:latest
```

Or with `compose.yaml`: `docker compose up -d` (published image), `docker compose up -d --build` (from sources).

The UI is at http://localhost:8080. The SQLite database lives in `/var/lib/chantier` (`chantier-data` volume), created or migrated on every startup.

| Variable | Default | Role |
|---|---|---|
| `APP_TIMEZONE` | `Europe/Paris` | Display timezone for dates |
| `DEFAULT_URI` | `http://localhost` | Base URL for links generated outside a request |
| `APP_STALE_HOURS` | `48` | Hours without activity before an in-progress ticket is flagged |
| `APP_SESSION_IDLE_HOURS` | `4` | Inactivity before an agent opens a new session |
| `APP_SECRET` | random on each startup | Symfony secret (no session or authentication uses it) |

Update: `docker compose pull && docker compose up -d`. Migrations are applied at startup.

The app runs as `www-data` (uid 82): the container starts as root, hands the data folder to that user, then drops root.

### NAS (SQLite database outside Docker)

```bash
CHANTIER_DATA_DIR=/volume1/docker/chantier docker compose -f compose.nas.yaml up -d
```

- `chantier.db` is created (or migrated) in `CHANTIER_DATA_DIR` at startup; default: `./data`.
- The folder must be on a local NAS disk, not on a mounted SMB/NFS share: SQLite needs reliable locks.
- The container runs with the uid/gid owning the data folder, `1000:1000` by default, since many NAS devices forbid the container from changing that owner. Other values: `CHANTIER_UID=1026 CHANTIER_GID=100 docker compose -f compose.nas.yaml up -d` (`ls -n` on the folder gives the right numbers).
- Backup: copy `chantier.db` with the container stopped, or live: `docker exec chantier sqlite3 /var/lib/chantier/chantier.db ".backup /var/lib/chantier/sauvegarde.db"`.

To reuse data from an existing Docker install (`chantier_chantier-data` volume):

```bash
docker compose stop
docker run --rm -v chantier_chantier-data:/src -v /volume1/docker/chantier:/dst alpine cp /src/chantier.db /dst/
```

### Local dev

```bash
composer install
composer db
php bin/console tailwind:build --watch   # in another terminal
composer serve
```

Listens on `0.0.0.0:8000`. Database: `var/data_dev.db`.

### Tests

```bash
composer test
```

CI (GitHub Actions) runs the tests, the PowerShell hook scenarios, then builds the image and checks it starts.

## Publish a release

```bash
git tag v0.2.0
git push origin v0.2.0
```

The `Publication` workflow reruns CI, scans the image (Trivy, blocking on fixable critical vulnerabilities), then publishes
`ghcr.io/dayofr/chantier` for amd64 and arm64 with SBOM and provenance. On first publication, make the package public:
*Packages → chantier → Package settings → Change visibility*.

## Connect Claude Code

From any machine on the network:

```bash
claude mcp add --transport http --scope user chantier http://<server-ip>:8080/mcp
```

This repo's `.mcp.json` already declares the server on `localhost:8080`.

For another tracked project (see [docs/claude-code.md](docs/claude-code.md) for `CLAUDE.md` instructions and session-summary hooks).

## MCP tools

| Tool | Role |
|---|---|
| `list_projects` | Projects and progress |
| `get_project` | Initiatives > epics > tickets tree, orphans, alerts |
| `create_project`, `update_project` | Project management |
| `create_initiative`, `update_initiative` | Initiative management |
| `create_epic`, `update_epic` | Epic management |
| `create_tickets` | Batch creation, with subtasks and dependencies (`#0` = 1st of the batch) |
| `update_ticket` | Status, priority, content, epic, comment |
| `get_ticket` | Full detail + 10 latest activities |
| `search_tickets` | Filters: project, epic, status, priority, type, label, text, orphans |
| `get_next_ticket` | In-progress ticket, otherwise the highest-priority unblocked one |
| `manage_subtasks` | Add, check, uncheck, delete |
| `set_dependency` | `blocks` or `relates_to`, cycle detection |
| `add_link`, `remove_link` | PR, commit, branch, file, URL; removals are logged |
| `log_activity`, `list_activity` | Log on a ticket, epic or initiative (`subject`); `list_activity` filters by text, type, ticket/epic/initiative, session |
| `start_session` | Names the session, returns summaries of previous sessions |
| `save_session_summary` | Full conversation summary and decisions made (no duplicates) |

Every MCP write is logged with the client name and its session. Without an MCP session (revision `2026-07-28`, used by Claude Code), a client's writes join its latest active session; see [docs/claude-code.md](docs/claude-code.md#séances).

## Security

No authentication: use on a trusted local network only.
The MCP server's DNS rebinding protection is disabled (`allowed_hosts: false`) to accept access by IP.

## Licence

[BSD 3-Clause](LICENSE) © DayoFr. Fonts: see [assets/fonts/LICENSES.md](assets/fonts/LICENSES.md).

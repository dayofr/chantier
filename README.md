# Chantier

Mini Jira pour suivre le travail de Claude Code. L'agent écrit via MCP, l'humain consulte.

- **Interface web** : `/` (lecture seule, fr/en, thème clair/sombre)
- **API REST** : `/api` (doc Swagger sur `/api/docs`)
- **Serveur MCP** : `/mcp` (HTTP streamable)
- **Stack** : Symfony 8.1, API Platform 4.3, SQLite, Twig + Tailwind 4 (AssetMapper, sans Node)

## Interface

| Page | URL |
|---|---|
| Portefeuille | `/fr` |
| Vue d'ensemble d'un projet | `/fr/projects/CHANT` |
| Kanban (filtre `?epic=CHANT-E4` ou `?epic=none`) | `/fr/projects/CHANT/board` |
| Initiative : description, décisions, Kanban (filtre `?epic=`) | `/fr/initiatives/CHANT-I4` |
| Détail ticket | `/fr/tickets/CHANT-12` |
| Activité (filtre `?session=…`) | `/fr/activity`, `/fr/projects/CHANT/activity` |

`/en/...` pour l'anglais. Les dates s'affichent dans le fuseau `APP_TIMEZONE` (défaut `Europe/Paris`), elles sont stockées en UTC.
Polices et icônes sont servies localement (`assets/fonts`), l'interface marche sans internet.
Les icônes sont un sous-ensemble : pour en utiliser une nouvelle, l'ajouter à `assets/fonts/icons.txt` puis lancer `php bin/download-fonts`.

La vue d'une initiative affiche sa description, ses décisions (posées sur l'initiative, ses epics ou leurs tickets, filtrées avec l'epic choisi) et le Kanban de ses tickets.
La description d'une initiative ou d'un epic sert au périmètre ; les choix faits se notent comme décisions, datées et rattachées à leur séance.

Un projet affiche des points d'attention : goulots, tickets en cours sans mouvement depuis `APP_STALE_HOURS` heures (48 par défaut), bloqués, sans epic.

## Modèle

```
Project (CHANT)
 └─ Initiative (CHANT-I1)
     └─ Epic (CHANT-E1)
         └─ Ticket (CHANT-12) : sous-tâches, dépendances, liens
Activity : journal (automatique + notes de l'agent)
```

Les clés servent d'identifiants partout : `/api/tickets/CHANT-12`, `get_ticket {"ticket": "CHANT-12"}`.

Statuts d'un ticket : `backlog`, `todo`, `in_progress`, `in_review`, `blocked`, `done`, `cancelled`.
Un ticket est aussi considéré bloqué si un ticket qui le bloque n'est pas terminé.

## Lancer

### Docker (usage courant)

```bash
docker compose up -d --build
```

Écoute sur le port 8080 de toutes les interfaces. La base vit dans le volume `chantier-data`.
Port différent : `CHANTIER_PORT=9000 docker compose up -d`.

### NAS (base SQLite hors Docker)

```bash
CHANTIER_DATA_DIR=/volume1/docker/chantier docker compose -f compose.nas.yaml up -d --build
```

- `chantier.db` est créée (ou migrée) dans `CHANTIER_DATA_DIR` au démarrage ; défaut : `./data`.
- Le dossier doit être sur un disque local du NAS, pas sur un partage SMB/NFS monté : SQLite a besoin de verrous fiables.
- Variables : `CHANTIER_PORT` (8080), `APP_TIMEZONE` (Europe/Paris), `DEFAULT_URI`.
- Sauvegarde : copier `chantier.db` conteneur arrêté, ou `sqlite3 chantier.db ".backup chantier-sauvegarde.db"` à chaud.
- Construit depuis les sources sur le NAS (image FrankenPHP disponible en amd64 et arm64).

Reprendre les données d'une installation Docker existante (volume `chantier_chantier-data`) :

```bash
docker compose stop
docker run --rm -v chantier_chantier-data:/src -v /volume1/docker/chantier:/dst alpine cp /src/chantier.db /dst/
```

### Dev local

```bash
composer install
composer db
php bin/console tailwind:build --watch   # dans un autre terminal
composer serve
```

Écoute sur `0.0.0.0:8000`. Base : `var/data_dev.db`.

### Tests

```bash
composer test
```

## Brancher Claude Code

Depuis n'importe quelle machine du réseau :

```bash
claude mcp add --transport http --scope user chantier http://<ip-du-serveur>:8080/mcp
```

Le fichier `.mcp.json` de ce dépôt déclare déjà le serveur sur `localhost:8080`.

Pour qu'un autre projet soit suivi (consignes `CLAUDE.md`, hooks pour le résumé de séance) : voir [docs/claude-code.md](docs/claude-code.md).

## Outils MCP

| Outil | Rôle |
|---|---|
| `list_projects` | Projets et avancement |
| `get_project` | Arbre initiatives > epics > tickets, orphelins, points d'attention |
| `create_project`, `update_project` | Gestion des projets |
| `create_initiative`, `update_initiative` | Gestion des initiatives |
| `create_epic`, `update_epic` | Gestion des epics |
| `create_tickets` | Création en lot, avec sous-tâches et dépendances (`#0` = 1er du lot) |
| `update_ticket` | Statut, priorité, contenu, epic, commentaire |
| `get_ticket` | Détail complet + 10 dernières activités |
| `search_tickets` | Filtres projet, epic, statut, priorité, type, label, texte, orphelins |
| `get_next_ticket` | Ticket en cours, sinon le plus prioritaire non bloqué |
| `manage_subtasks` | Ajouter, cocher, décocher, supprimer |
| `set_dependency` | `blocks` ou `relates_to`, détection des cycles |
| `add_link`, `remove_link` | PR, commit, branche, fichier, URL ; retrait noté au journal |
| `log_activity`, `list_activity` | Journal sur un ticket, un epic ou une initiative (`subject`) ; `list_activity` filtre par texte, type, ticket/epic/initiative, session |
| `start_session` | Nomme la séance, renvoie les résumés des séances précédentes |
| `save_session_summary` | Résumé complet de la conversation et décisions prises (sans doublon) |

Chaque écriture MCP est journalisée avec le nom du client et sa séance. Sans session MCP (révision `2026-07-28`, utilisée par Claude Code), les écritures d'un client rejoignent sa dernière séance active ; voir [docs/claude-code.md](docs/claude-code.md#séances).

## Sécurité

Aucune authentification : usage sur réseau local de confiance uniquement.
La protection DNS rebinding du serveur MCP est désactivée (`allowed_hosts: false`) pour accepter les accès par IP.

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
| Détail ticket | `/fr/tickets/CHANT-12` |
| Activité (filtre `?session=…`) | `/fr/activity`, `/fr/projects/CHANT/activity` |

`/en/...` pour l'anglais. Les dates s'affichent dans le fuseau `APP_TIMEZONE` (défaut `Europe/Paris`), elles sont stockées en UTC.
Les polices et icônes viennent de Google Fonts : sans accès internet, l'interface retombe sur les polices système.

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

Pour qu'un autre projet soit suivi, ajouter à son `CLAUDE.md` :

```markdown
## Suivi de projet
Ce projet est suivi dans Chantier (MCP `chantier`, clé projet `XXX`).
- Début de session : `list_activity` puis `get_next_ticket`.
- Avant de coder : passer le ticket en `in_progress`. Pas de travail hors ticket : sinon `create_tickets`.
- Pendant : `log_activity` pour décisions, blocages, commits, résultats de tests.
- Fin : cocher les sous-tâches, `add_link` pour commits/PR, statut `in_review` ou `done`.
```

## Outils MCP

| Outil | Rôle |
|---|---|
| `list_projects` | Projets et avancement |
| `get_project` | Arbre initiatives > epics > tickets, orphelins |
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
| `add_link` | PR, commit, branche, fichier, URL |
| `log_activity`, `list_activity` | Journal |

Chaque écriture MCP est journalisée avec le nom du client et l'id de session MCP.

## Sécurité

Aucune authentification : usage sur réseau local de confiance uniquement.
La protection DNS rebinding du serveur MCP est désactivée (`allowed_hosts: false`) pour accepter les accès par IP.

# CLAUDE.md

@AGENTS.md
@README.md

## Conventions du projet

- Écrits en français : messages d'erreur, descriptions des outils MCP, doc.
- Outils MCP : une classe DTO dans `src/Mcp/Tool` (attribut `#[McpTool]`, `structuredContent: false`)
  et un processor dans `src/Mcp/Processor` qui étend `AbstractToolProcessor`.
  Les enums sont passés en `string` avec `ApiProperty(schema: enum)`, convertis par `$this->enum()`.
- Erreur métier : lancer `ToolError`. Le message est renvoyé tel quel à l'agent.
- Réponses MCP : construites par `Presenter`, pas par le serializer.
- Journal : créations et changements de statut sont écrits par `ActivityRecorder` (listener Doctrine).
- Chaque nouvel outil a un test dans `tests/Mcp`.
- UI : contrôleurs GET uniquement (un test le vérifie). Composants dans `templates/_ui.html.twig`.
  Couleurs via les tokens de `assets/styles/app.css`, jamais de couleurs Tailwind brutes.
- Textes d'interface : clés dans `translations/messages+intl-icu.{fr,en}.yaml`, toujours les deux langues.
  Les enums implémentent `TranslatableInterface` : `{{ status|trans }}`.
- Icônes Material Symbols auto-hébergées en sous-ensemble : toute nouvelle icône va dans `assets/fonts/icons.txt`,
  puis `php bin/download-fonts`. Un test échoue sinon.
- Logs de test dans `var/log/test.log`, pas dans la sortie PHPUnit.

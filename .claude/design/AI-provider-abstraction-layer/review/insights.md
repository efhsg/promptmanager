# Insights

## Codebase onderzoek

### Vergelijkbare features
- AiCompletionClient interface: `yii/services/AiCompletionClient.php` — al bestaande abstractie voor single-turn completions
- ClaudeQuickHandler: `yii/handlers/ClaudeQuickHandler.php` — accepteert AiCompletionClient interface, al provider-agnostisch
- ClaudeCliCompletionClient: `yii/services/ClaudeCliCompletionClient.php` — concrete implementatie van AiCompletionClient

### Herbruikbare componenten
- AiCompletionClient interface: hergebruiken als basis voor provider-abstractie
- ClaudeQuickHandler: hoeft niet te wijzigen (gebruikt al interface)
- ClaudeStreamRelayService: NDJSON relay patroon is generiek, kan hergebruikt worden
- ClaudeRunStatus enum: async task lifecycle patroon is generiek
- RBAC owner rule patroon: standaard Yii2 RBAC, herbruikbaar

### Te volgen patterns
- Service layer pattern: `yii/services/` — DI in constructors
- Query class pattern: `yii/models/query/` — chainable scopes
- Enum pattern: `yii/common/enums/` — backed enums met labels/values
- RBAC owner rules: `yii/rbac/` — user_id ownership checks

### Omvang van Claude-afhankelijkheden
- **Models (3)**: ClaudeRun, ClaudeRunSearch, ClaudeRunQuery
- **Services (5)**: ClaudeCliService, ClaudeCliCompletionClient, ClaudeWorkspaceService, ClaudeStreamRelayService, ClaudeRunCleanupService
- **Controllers (3)**: ClaudeController (web), ClaudeRunController (console), ClaudeController (console)
- **Enums (2)**: ClaudeRunStatus, ClaudePermissionMode
- **RBAC (1)**: ClaudeRunOwnerRule
- **Jobs (1)**: RunClaudeJob
- **Handlers (1)**: ClaudeQuickHandler
- **Views (3 dedicated + 13 integratieviews)**: claude/index, claude/runs, claude/cleanup-confirm + note/view, prompt-instance/view, etc.
- **CSS (3)**: claude-chat.css (1582 lines), site.css, mobile.css
- **JS (1)**: editor-init.js
- **Migrations (5)**: claude_run tabel + project kolommen
- **Tests (17)**: unit tests voor alle Claude classes + fixtures
- **Config (2)**: main.php (DI registratie), rbac.php (permissions)
- **Project model**: claude_options, claude_context, claude_permission_mode properties

### Reeds geabstraheerd
- AiCompletionClient interface — generiek contract voor single-turn completions
- ClaudeQuickHandler — gebruikt alleen de interface, niet de concrete klasse

## Beslissingen

1. **Minimale basis-interface + optionele feature-interfaces**: ISP-conform, providers hoeven alleen het minimum te implementeren
2. **`instanceof`-checks**: Controllers detecteren optionele features zonder configuratie-overhead
3. **CSS-klassen en HTML IDs NIET hernoemen**: 100+ interne namen, geen functioneel voordeel
4. **`convertToMarkdown()` niet op interface**: Format-concern, hoort bij `CopyFormatConverter`
5. **Provider display naam via `getName()`**: Dynamisch, geen statisch config-param als primaire bron
6. **`executeStreaming()` signature matcht bestaande code**: `$onLine` op positie 3
7. **RBAC migratie**: Moet `auth_item`/`auth_item_child` tabellen updaten naast config
8. **Één migratie**: Tabel rename + provider kolom in één `safeUp()`
9. **Test file migratie**: 17 bestanden hernoemd, 3 nieuwe interface compliance tests

## Consistentiecheck

| Check | Status |
|-------|--------|
| Wireframe ↔ Componenten | Passed — labels via `getName()` consistent |
| Frontend ↔ Backend | Passed — JS URLs matchen endpoint tabel |
| Edge cases ↔ Tests | Passed — 1-op-1 mapping |
| Architectuur ↔ Locaties | Passed — alle paden in component tabel |
| Security ↔ Endpoints | Passed — endpoint tabellen nu consistent (1 contradictie gecorrigeerd) |

### Gecorrigeerde contradicties
- Endpoint tabel onder "Technische overwegingen" miste 4 endpoints (`suggest-name`, `index`, `save`, `cleanup`) die WEL in security tabel stonden — toegevoegd
- Wireframe label bron verwees naar `params['aiProviderLabel']` i.p.v. `getName()` — gecorrigeerd

## Open vragen
Geen

## Blokkades
Geen

## Ronde 2 — Bevindingen

### Architect ronde 2 — 35 gaps gevonden, 27 opgelost in spec

Belangrijkste nieuwe beslissingen:
10. **Stream result parsing op provider**: `parseStreamResult()` op `AiStreamingProviderInterface` — verplaatst Claude-specifieke NDJSON parsers uit `RunAiJob`
11. **`getGitBranch()` niet op interface**: Git branch is geen provider-verantwoordelijkheid, verplaats naar utility/controller
12. **`convertToMarkdown()` via `CopyFormatConverter` DI**: Tweede constructor dependency in `AiChatController`
13. **`claudeWorkspaceService` component verwijderen**: Na merge in `ClaudeCliProvider`, via DI i.p.v. named component
14. **`hasConfig()` retourneert `array`**: Diagnostische array met provider-specifieke keys, niet `bool`
15. **HTML IDs in `project/_form.php` NIET hernoemen**: Intern binnen dezelfde view, geen functionele noodzaak
16. **`AiRun::tableName()` met `{{%}}` prefix**: Fix van bestaande inconsistentie

## Consistentiecheck ronde 2

| Check | Status |
|-------|--------|
| Wireframe ↔ Componenten | Passed — labels via `getName()` consistent, page titles, breadcrumbs, chat role labels, bottom nav |
| Frontend ↔ Backend | Passed — JS URLs matchen endpoint tabel, sessionStorage read+write sides gedekt |
| Edge cases ↔ Tests | Passed — 16 edge cases → 20 edge case tests (1-op-1 + extra dekking) |
| Architectuur ↔ Locaties | Passed — 24 componenten met paden, console commands nu meegenomen |
| Security ↔ Endpoints | Passed — 21 endpoints in security tabel matchen endpoint + integratie tabellen |
| RBAC ↔ Controllers | Passed — entity keys matchen controller IDs (ai-chat, aiRun) |

### Gecorrigeerde contradicties ronde 2
- RBAC tabel: "Permission `claude` → `ai`" was fout — `claude` is een entity key (controller mapping), geen standalone permission. Gecorrigeerd naar "Entity key"
- RBAC tabel: Dubbele rijen `viewClaudeRun`/`updateClaudeRun` verwijderd
- HTTP verb: `DELETE /ai/delete-session` gecorrigeerd naar `POST` (zowel in endpoint tabel als security tabel)

## Eindresultaat
Ronde 2 voltooid. Alle 6 reviews score 9/10. Consistentiecheck passed met 3 kleine correcties. Spec is implementatie-klaar.

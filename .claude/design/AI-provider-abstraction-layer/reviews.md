# Reviews

## Review: Architect — 2026-02-16

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success, provider niet beschikbaar)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment (labels → params, structuur ongewijzigd)
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Kernprincipe helder: minimale interface + optionele interfaces is een gezond patroon dat ISP (Interface Segregation Principle) respecteert
- `instanceof`-checks voor optionele features voorkomt configuratie-overhead
- CSS-klassen NIET hernoemen is pragmatisch en correct — interne naamgeving raakt geen externe API
- Bestaande `AiCompletionClient` en `CopyFormatConverter` worden hergebruikt in plaats van opnieuw uitgevonden
- DI-configuratie is minimaal: enkel de basis-interface wordt geregistreerd

### Verbeterd
- `convertToMarkdown()` verwijderd van `AiProviderInterface` — dit is een format-concern dat bij `CopyFormatConverter` hoort, niet bij providers
- Gebruikersflow stap 5 gecorrigeerd: checkt nu `AiStreamingProviderInterface` met sync fallback
- Edge case tabel: `AiWorkspaceInterface` → `AiWorkspaceProviderInterface` (naamconsistentie)
- Test scenario: `ClaudeWorkspaceProvider implements AiWorkspaceInterface` → `ClaudeCliProvider implements alle optionele interfaces`
- Stream storage path rename (`storage/claude-runs/` → `storage/ai-runs/`) toegevoegd als edge case
- Provider-resolutie in `RunAiJob` verduidelijkt: resolved via DI (v1), provider-kolom als groeipunt
- `AiPermissionMode`: toegevoegd dat providers onbekende modes mogen negeren + `getSupportedPermissionModes()` op `AiConfigProviderInterface`
- Noot toegevoegd dat `convertToMarkdown()` niet bij provider hoort maar bij `CopyFormatConverter`

### Nog open
- Geen

## Review: Architect (ronde 2) — 2026-02-16

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Alle fundamentele architectuurbeslissingen uit ronde 1 zijn solide en ongewijzigd
- ISP interface-hiërarchie is helder en implementeerbaar
- Componenten-tabel is nu volledig met console commands, cleanup details en provider delegation
- Endpoint tabel volledig: alle 20 web actions + ProjectController integratie
- Queue channel migratie heeft nu een concreet migratiestap (UPDATE queue)
- Stream result parsing verplaatst naar provider — `RunAiJob` is nu provider-agnostisch

### Verbeterd (13 critical + 14 medium gaps opgelost)
- **ProjectController integratie** toegevoegd: `actionClaudeCommands` → `actionAiCommands`, deprecated `actionClaude`, `loadClaudeOptions()` → `loadAiOptions()` met form field rename
- **NoteController integratie** toegevoegd: `actionClaude` → `actionAi` met redirect update
- **RBAC volledig**: entity keys (`claude` → `ai-chat`, `claudeRun` → `aiRun`), roles sectie, project/note entity entries — 12 rename-items i.p.v. 6
- **`claudeWorkspaceService` component** verwijdering gespecificeerd: config/main.php registratie + `Project::afterSave()`/`afterDelete()` calls via DI
- **`RunAiJob` NDJSON parsing** verplaatst: Claude-specifieke `extractResultText()`, `extractMetadata()`, `extractSessionId()` naar `ClaudeCliProvider::parseStreamResult()` via `AiStreamingProviderInterface`
- **`getGitBranch()`** niet op interface — verplaats naar utility of inline in controller
- **`convertToMarkdown()`** controller-dependency opgelost: `CopyFormatConverter` als tweede DI-dependency in `AiChatController`
- **Layout controller ID checks**: `=== 'claude'` → `=== 'ai-chat'` op 2 plaatsen in `main.php` + bottom nav
- **HTTP verb** gecorrigeerd: `DELETE /ai/delete-session` → `POST` (matcht VerbFilter)
- **`hasConfig()` return type**: `bool` → `array` (diagnostische response, provider-specifieke keys toegestaan)
- **FR-3 Project model**: 9 methoden expliciet benoemd i.p.v. "etc.", attribute labels, `afterSave()` relevantFields, workspace DI-calls
- **Endpoint security tabel**: 8 ontbrekende endpoints toegevoegd (active-runs, summarize*, stream, cancel, import*, project/ai-commands)
- **sessionStorage schrijfzijde**: 5 integratie-views die `claudePromptContent` schrijven expliciet benoemd
- **Permission mode view**: hardcoded array vervangen door dynamische `getSupportedPermissionModes()` call
- **Console commands**: `actionCleanupFiles` path hardcode, `actionDiagnose` binary-check delegation, constructor DI-fallback voor beide services
- **Queue migratie stap**: `UPDATE queue SET channel = 'ai' WHERE channel = 'claude'` als migratiestap
- **`AiRun::tableName()`**: `{{%ai_run}}` met prefix syntax (fix van huidige `'claude_run'` zonder prefix)
- **Private helpers**: volledige lijst (14 methoden) i.p.v. 3 voorbeelden
- **`RunAiJob::createQuickHandler()`**: factory method update naar `AiQuickHandler`
- **Workspace configuratie-generatie**: `generateClaudeMd()` en `generateSettingsJson()` als provider-specifieke public methods op concrete klasse gedocumenteerd

### Nog open
- Geen

## Review: Security — 2026-02-16

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- RBAC rules consistent met bestaand patroon (owner-based access control)
- Stream file path constructie via integer PK voorkomt path traversal
- Endpoint tabel bevat RBAC regels per endpoint
- Provider identifier als string met validatie voorkomt ongewenste waarden

### Verbeterd
- **Provider identifier validatie** toegevoegd: regex `/^[a-z][a-z0-9-]{1,48}$/` als model rule op `AiRun.provider`
- **CLI command injection** risico gedocumenteerd: provider MOET prompt escapen voor shell
- **RBAC migratie** volledig uitgewerkt: niet alleen `rbac.php` config maar ook `auth_item`/`auth_item_child` database tabellen
- **Endpoint security tabel** uitgebreid: `suggest-name`, `index`, `save`, `cleanup` endpoints toegevoegd die in de views werden gerefereerd maar niet in de endpoint tabel stonden
- **Provider options sanitizatie**: eis toegevoegd dat providers onbekende keys in `ai_options` moeten negeren
- **Security test scenarios** toegevoegd: provider identifier met speciale chars, RBAC migratie integrity, stream file path traversal

### Nog open
- Geen

## Review: Security (ronde 2) — 2026-02-16

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Ronde 1 verbeteringen (provider identifier regex, RBAC migratie, endpoint security tabel) zijn correct doorgevoerd
- Endpoint security tabel nu compleet met alle 21 endpoints (20 web + 1 ProjectController)
- RBAC rename-tabel is uitgebreid van 6 naar 12 items — dekt nu entity keys, roles sectie, project/note entries
- Provider identifier wordt niet geïnterpoleerd in file paths (integer PK voor stream files)
- `instanceof`-checks voor optionele features voorkomt dat minimale providers security-gevoelige methoden moeten implementeren

### Verbeterd (5 security gaps opgelost)
- **Queue job deserialisatie**: `class_alias` strategie toegevoegd — voorkomt dat bestaande geserialiseerde `RunClaudeJob` jobs in de queue tabel silent falen na class rename. Zonder alias: `unserialize()` gooit "Class not found" error, jobs verdwijnen
- **Provider identifier CLI restriction**: Expliciete eis dat provider identifier NOOIT in shell commands geïnterpoleerd mag worden. CLI binary selectie via whitelist map, niet string concatenatie
- **Credential path validatie**: Provider credentials MOETEN via `realpath()` + prefix check gevalideerd worden. Huidige `resolveCredentialsPath()` accepteert ongevalideerd pad uit `params['claudeCredentialsPath']`
- **Stream file permissions**: `0640` permissions gespecificeerd voor stream files. Stream files bevatten volledige prompts/responses (potentieel gevoelige data). Directory MOET buiten webroot
- **RBAC migratie volgorde**: Concrete 6-stappen volgorde toegevoegd (nieuwe rule eerst, dan rename, dan oude rule verwijderen). Voorkomt referentieel integriteitsverlies bij tussentijdse failures
- **DI config scope**: Console.php en test.php moeten ook DI bindings bevatten — niet alleen main.php

### Nog open
- Geen

## Review: UX/UI Designer — 2026-02-16

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, empty, error, success, provider unavailable, streaming not supported, feature not supported)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Wireframe helder: structuur wijzigt niet, enkel labels — minimale visuele impact
- CSS-klassen behouden: geen risico op broken styling
- UI states dekken alle relevante scenario's inclusief provider-specifieke degradatie
- Bestaande UI componenten (Bootstrap alerts, modals, thinking dots) worden hergebruikt

### Verbeterd
- **Provider naam bron**: gewijzigd van statisch `params['aiProviderLabel']` naar dynamisch `getName()` met fallback — voorkomt configuratie-mismatch
- **Provider icon**: `getIcon()` methode toegevoegd als optioneel op interface — providers kunnen eigen icon meegeven
- **"Provider niet beschikbaar" state**: verduidelijkt dat prompt editor disabled wordt maar chat history leesbaar blijft
- **"Streaming niet ondersteund" state**: nieuwe UI state voor providers zonder streaming — spinner i.p.v. dots
- **"Feature niet ondersteund" state**: UI-secties (Usage, Settings, Commands) worden verborgen als provider de interface niet implementeert
- **FR-6 permission modes**: UI toont alleen modes die de provider rapporteert als ondersteund

### Nog open
- Geen

## Review: Front-end Developer — 2026-02-16

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Pragmatische keuze om CSS-klassen en HTML IDs niet te hernoemen — voorkomt brede regressie in 1582+ lijnen CSS
- sessionStorage fallback patroon is helder en voorkomt dataverlies
- Integratie-views (note, prompt-instance) zijn meegenomen in scope
- `editor-init.js` referentie naar `.claude-prompt-card-sticky` is correct als niet-hernoemd geïdentificeerd

### Verbeterd
- **sessionStorage fallback**: concreet code-patroon toegevoegd (lees nieuwe key eerst, fallback legacy, schrijf nieuwe, verwijder legacy)
- **Integratie-views URL updates**: expliciet benoemd dat `note/view.php`, `prompt-instance/view.php` etc. URL-updates nodig hebben
- **HTML IDs niet hernoemen**: expliciet gedocumenteerd dat `#claudeStreamModal`, `#claudePromptCard` etc. behouden blijven
- **`site.css` en `editor-init.js`**: referenties naar Claude-specifieke klassen/selectors expliciet gedocumenteerd als niet-hernoemd
- **Layout en mobile.css**: import-wijzigingen gespecificeerd

### Nog open
- Geen

## Review: UX/UI Designer (ronde 2) — 2026-02-16

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, empty, error, success, provider unavailable, streaming not supported, feature not supported)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Alle ronde 1 UX-verbeteringen zijn correct doorgevoerd en consistent
- Provider naam bron (`getName()` + fallback) is nu volledig gespecificeerd
- UI states dekken alle degradatiescenario's voor minimale providers
- CSS-klassen behouden: zero risk op visuele regressie
- Permission mode dropdown nu dynamisch i.p.v. hardcoded array

### Verbeterd
- **Inline JS string labels**: hardcoded "Claude thinking", "Claude {status}", role label "Claude" in chat bubbles — nu expliciet gespecificeerd als te vervangen door provider naam via PHP variabele in inline JS
- **Page titles en breadcrumbs**: "Claude CLI" en "Claude Sessions" in views `index.php`, `runs.php`, `cleanup-confirm.php` — expliciet benoemd
- **Bottom nav**: hardcoded "Claude" label en `isActive('claude')` check — nu gespecificeerd als dynamisch
- **Layout controller ID checks**: `=== 'claude'` → `=== 'ai-chat'` op 2 plaatsen — voorkomt silent breakage van nav active state en pagina-specifieke CSS-loading

### Nog open
- Geen

## Review: Front-end Developer (ronde 2) — 2026-02-16

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- sessionStorage fallback patroon is nu volledig: zowel lees- als schrijfzijde gedekt in alle integratie-views
- Layout controller ID check correctie voorkomt subtiele bug (nav highlighting stopt met werken)
- Inline JS string updates zijn concreet gespecificeerd per locatie
- Permission mode dropdown dynamisch via provider interface — clean separation
- HTML IDs in `project/_form.php` expliciet als "niet hernoemen" gedocumenteerd — voorkomt scope creep

### Verbeterd
- **sessionStorage schrijfzijde**: 5 integratie-views (`prompt-instance/_form.php`, `prompt-instance/view.php`, `prompt-instance/index.php`, `note/view.php`, `note/index.php`) die `claudePromptContent` SCHRIJVEN — nu expliciet benoemd. Ronde 1 dekte alleen de leeszijde
- **Project form fields**: `claude_options[...]` → `ai_options[...]` rename gespecificeerd inclusief `loadAiOptions()` helper — vereist ook HTML form field names update
- **Inline JS provider naam**: concreet patroon voor PHP → JS provider naam binding gespecificeerd (via PHP variabele in inline script)

### Nog open
- Geen

## Review: Developer (ronde 2) — 2026-02-16

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Interface-hiërarchie is nu compleet: `parseStreamResult()` op `AiStreamingProviderInterface` lost het NDJSON-coupling probleem in `RunAiJob` op
- Alle 14 private helpers expliciet benoemd — implementeerder weet exact wat op concrete klasse blijft
- `getGitBranch()` en `convertToMarkdown()` dependency-resolutie zijn concreet (utility/inline resp. DI second dependency)
- Console commands hebben nu DI-fallback updates en `actionDiagnose` provider-delegatie gespecificeerd
- Queue job class alias strategie is pragmatisch en veilig — minimale code, maximale compatibiliteit
- RBAC migratie heeft concrete 6-stappen implementatievolgorde

### Verbeterd
- **`parseStreamResult()` op interface**: Claude-specifieke NDJSON parsers uit `RunAiJob` verplaatst naar `AiStreamingProviderInterface`. `RunAiJob` is nu volledig provider-agnostisch
- **`hasConfig()` return type**: `bool` → `array` — matcht werkelijke implementatie die diagnostische array retourneert
- **`createQuickHandler()` factory**: Expliciet benoemd als te updaten naar `AiQuickHandler`
- **Console command `actionDiagnose`**: Hardcoded `which claude` binary check delegeren naar provider — elke provider kent zijn eigen binary
- **`actionCleanupFiles` dubbele path**: Hardcoded storage path in console command onafhankelijk van model method — beide moeten worden bijgewerkt
- **`claudeWorkspaceService` component verwijdering**: Concreet migratie-pad voor `Project::afterSave()`/`afterDelete()` van named component naar DI-resolutie
- **Class alias voor queue**: Concrete strategie (`class_alias` in bootstrap) i.p.v. vage "drain queue first" instructie

### Nog open
- Geen

## Review: Tester (ronde 2) — 2026-02-16

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Ronde 1 test file migratie-tabel (17 bestanden) en interface compliance tests zijn intact en correct
- Edge case tests zijn meetbaar en concreet — elke test heeft specifieke input en verwacht resultaat
- Nieuwe edge cases uit ronde 2 (queue job alias, RBAC rollback, parseStreamResult, Project afterSave) zijn 1-op-1 testbaar

### Verbeterd
- **7 nieuwe edge case tests** toegevoegd uit ronde 2 bevindingen:
  - Queue job class alias deserialisatie test
  - RBAC migratie rollback test (safeDown)
  - `parseStreamResult` met Claude NDJSON test
  - `parseStreamResult` met leeg stream-log test
  - `ProjectController::actionAiCommands` test
  - `Project::afterSave` workspace sync via DI test
- **Regressie-dekking verbreed**: Nu 20+ edge case tests die alle critical gaps uit de deep dive dekken
- **Fixture data**: Geen nieuwe fixture wijzigingen nodig — bestaande fixture updates uit ronde 1 zijn voldoende

### Nog open
- Geen

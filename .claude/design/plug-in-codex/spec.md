# Feature: plug-in-codex

## Samenvatting

Maak het toevoegen van nieuwe AI CLI providers (Codex, Gemini, etc.) zo eenvoudig als het implementeren van een provider-klasse en het registreren in de DI-config. Elke provider is op projectniveau configureerbaar via een eigen tab in de project-instellingen, met provider-specifieke opties die niet beperkt worden door een gedeelde interface.

## User story

Als gebruiker wil ik Codex CLI als tweede AI provider kunnen gebruiken in PromptManager, zodat ik per project kan kiezen welke CLI ik gebruik en elke provider zijn eigen configuratie-opties heeft (bijv. `approval-mode` voor Codex i.p.v. `permission-mode` voor Claude).

## Functionele requirements

### FR-0: Infrastructuur — Docker & Codex binary

- Beschrijving: De Docker-omgeving wordt voorbereid voor een tweede CLI provider (Codex). Dit omvat credential mounts en binary installatie.
- Acceptatiecriteria:
  - [ ] `docker-compose.yml`: `~/.codex/` mount op zowel `pma_yii` als `pma_queue` services
  - [ ] Dockerfile: `npm install -g @openai/codex` toegevoegd
  - [ ] `codex --version` draait succesvol in de container
  - [ ] `codex auth` kan uitgevoerd worden; credentials persistent via mount
  - [ ] Credential directory mount is read-only waar mogelijk (`~/.codex/:ro`) tenzij `codex auth` schrijfrechten vereist; in dat geval: mount read-write maar documenteer in `.env.example`

### FR-1: Provider declareert eigen configuratie-schema

- Beschrijving: `AiConfigProviderInterface` wordt uitgebreid met `getConfigSchema()` zodat elke provider zijn eigen configuratie-opties kan declareren. Dit schema drijft zowel de project form tabs als de chat view custom velden.
- Acceptatiecriteria:
  - [ ] `AiConfigProviderInterface` bevat methode `getConfigSchema(): array`
  - [ ] Return format: `array<string, array{type: 'select'|'text'|'textarea'|'checkbox', label: string, hint?: string, placeholder?: string, options?: array<string, string>, default?: mixed, group?: string}>`
  - [ ] `ClaudeCliProvider::getConfigSchema()` declareert: `allowedTools` (text), `disallowedTools` (text), `appendSystemPrompt` (textarea)
  - [ ] Toekomstige `CodexCliProvider::getConfigSchema()` declareert: `approvalMode` (select met opties suggest/auto-edit/full-auto)

### FR-2: Per-provider project-opties opslag

- Beschrijving: Provider-specifieke opties worden genamespaced per provider identifier opgeslagen in de bestaande `ai_options` JSON kolom van `Project`.
- Acceptatiecriteria:
  - [ ] `Project::getAiOptionsForProvider(string $identifier): array` — retourneert opties voor één provider
  - [ ] `Project::setAiOptionsForProvider(string $identifier, array $options): void` — slaat op genamespaced; filtert lege values (consistent met bestaand `setAiOptions()` gedrag)
  - [ ] `Project::getDefaultProvider(): string` — retourneert `_default` key, of `'claude'` als hardcoded fallback (model mag niet afhankelijk zijn van `AiProviderRegistry`)
  - [ ] `Project::getAiOptions(): array` — backward-compatible: retourneert opties van de default provider
  - [ ] `Project::getAiCommandBlacklist(?string $provider = null)` en `getAiCommandGroups(?string $provider = null)` worden provider-aware: lezen uit `getAiOptionsForProvider($provider ?? $this->getDefaultProvider())`. Huidige callers zonder parameter behouden bestaand gedrag.
  - [ ] Legacy flat `ai_options` JSON wordt bij lezen automatisch herkend en bij eerste save gemigreerd naar genamespaced structuur onder `claude` key
  - [ ] Opslag format: `{"claude": {"model": "sonnet", ...}, "codex": {"model": "codex-mini", ...}, "_default": "claude"}`

### FR-3: Per-provider tabs in project form

- Beschrijving: De project form toont dynamisch een tab per geregistreerde provider die `AiConfigProviderInterface` implementeert, in plaats van de huidige hardcoded "Claude CLI Defaults" card.
- Acceptatiecriteria:
  - [ ] `ProjectController` constructor injection wijzigt van `AiProviderInterface $aiProvider` naar `AiProviderRegistry $providerRegistry`
  - [ ] Controller geeft `$this->providerRegistry->all()` door aan de project form view
  - [ ] Per provider die `AiConfigProviderInterface` implementeert: één tab
  - [ ] Tab-label = `$provider->getName()`
  - [ ] Tab-inhoud bevat: Model dropdown (uit `getSupportedModels()`), Permission Mode dropdown (uit `getSupportedPermissionModes()`), provider-specifieke velden (uit `getConfigSchema()`), command dropdown config (alleen als provider `loadCommands()` ondersteunt)
  - [ ] Context tab (Quill editor voor `ai_context`) blijft apart als eigen tab — is provider-agnostisch
  - [ ] Bij slechts één provider: geen tabs, directe weergave (huidige UX)
  - [ ] Form data wordt opgeslagen als `ai_options[{provider_id}][{option_key}]`
  - [ ] `ProjectController::loadAiOptions()` itereert over POST data per provider key, valideert dat de key een geregistreerde provider identifier is via `AiProviderRegistry::has()`, en roept `setAiOptionsForProvider()` aan per geldige provider. Onbekende provider keys worden genegeerd (geen error, data wordt niet opgeslagen).
  - [ ] `ProjectController::actionCreate()` en `actionUpdate()` gebruiken beide de gewijzigde `loadAiOptions()`
  - [ ] `ProjectController::actionUpdate()` → `projectConfigStatus` check wordt provider-aware: checkt config per provider die `AiConfigProviderInterface` implementeert
  - [ ] `ProjectController::actionAiCommands()` accepteert optionele `provider` parameter; valideert tegen `AiProviderRegistry::has()` (onbekende provider → fallback naar default). Retourneert commands voor de opgegeven provider.

### FR-4: Provider-specifieke opties doorstromen naar CLI

- Beschrijving: Bij het aanmaken van een run worden project-defaults van de geselecteerde provider samengevoegd met runtime-selectie uit de chat view. De huidige `allowedKeys` whitelist in `prepareRunRequest()` wordt verwijderd.
- Acceptatiecriteria:
  - [ ] `prepareRunRequest()` leest defaults via `$project->getAiOptionsForProvider($providerIdentifier)` i.p.v. `$project->getAiOptions()`
  - [ ] `allowedKeys` whitelist verwijderd — vervangen door provider-driven validatie: `prepareRunRequest()` accepteert alle keys, maar de provider's `buildCommand()` vertaalt alleen bekende keys naar CLI flags (whitelist-per-provider in plaats van whitelist-in-controller)
  - [ ] Provider-specifieke keys worden niet gefilterd door de controller
  - [ ] `buildCommand()` in elke provider vertaalt eigen `$options` keys naar CLI flags met `escapeshellarg()` voor alle string values (command injection preventie)
  - [ ] Onbekende keys worden door de provider genegeerd (niet doorgestuurd als CLI flags)

### FR-5: Chat view dynamische provider-opties

- Beschrijving: De chat view toont provider-specifieke opties die dynamisch wisselen bij provider-selectie. Naast model en permission mode worden custom velden uit `getConfigSchema()` getoond.
- Acceptatiecriteria:
  - [ ] `buildProviderData()` bevat naast `models` en `permissionModes` ook `configSchema` per provider
  - [ ] Provider-specifieke velden worden dynamisch gerenderd in het settings panel
  - [ ] Bij provider-wissel: velden wisselen mee (bestaande Alpine.js `repopulateSelect` pattern)
  - [ ] Custom velden waarden worden meegestuurd in het request body via `getOptions()`
  - [ ] Project defaults voor custom velden worden geprefilled via `prefillFromDefaults()`
  - [ ] Dynamisch gerenderde veld-labels, hints en option-teksten uit `configSchema` worden ge-escaped via `Html::encode()` (server-side) of DOM `textContent` (client-side) — XSS-preventie bij kwaadwillende schema-data

### FR-6: Workspace management per provider

- Beschrijving: Elke provider die `AiWorkspaceProviderInterface` implementeert beheert zijn eigen workspace. De bestaande `Project::afterSave()` workspace sync wordt provider-aware via `AiProviderRegistry`.
- Acceptatiecriteria:
  - [ ] `Project::afterSave()` resolved `AiProviderRegistry` via `Yii::$container` en itereert alle providers die `AiWorkspaceProviderInterface` implementeren (huidige code doet al `Yii::$container->get()` — dit wijzigt van `AiProviderInterface` naar `AiProviderRegistry`)
  - [ ] Elke provider schrijft naar eigen workspace directory: `@app/storage/projects/{project_id}/{provider_id}/`
  - [ ] `ClaudeCliProvider::getWorkspacePath()` retourneert `storage/projects/{id}/claude/` (wijziging van huidige `storage/projects/{id}`)
  - [ ] `Project::afterDelete()` wordt eveneens provider-aware: resolved `AiProviderRegistry` via `Yii::$container` en itereert alle workspace providers voor `deleteWorkspace()` (huidige code doet single-provider delete)
  - [ ] `ProjectController::actionDelete()` hoeft geen expliciete workspace cleanup te doen — `afterDelete()` handelt dit af. Controller roept alleen `$model->delete()` aan (bestaand gedrag).
  - [ ] Bestaande workspace directories worden bij eerste access gemigreerd van `{id}/` naar `{id}/claude/`

### FR-7: Codex CLI als eerste plug-in provider

- Beschrijving: `CodexCliProvider` wordt geïmplementeerd als proof-of-concept tweede provider.
- Acceptatiecriteria:
  - [ ] Bestand: `yii/services/ai/providers/CodexCliProvider.php`
  - [ ] Implementeert: `AiProviderInterface`, `AiStreamingProviderInterface`, `AiConfigProviderInterface`
  - [ ] `getIdentifier()`: `'codex'`
  - [ ] `getName()`: `'Codex'`
  - [ ] `getSupportedModels()`: models uit Codex CLI (`codex-mini`, etc.)
  - [ ] `getSupportedPermissionModes()`: retourneert lege array (Codex gebruikt `approval-mode` via `getConfigSchema()`)
  - [ ] `getConfigSchema()`: declareert `approvalMode` select
  - [ ] `buildCommand()`: vertaalt opties naar `codex exec --approval-mode {mode} --json ...`; alle user-controlled values via `escapeshellarg()` (volgt bestaand Claude pattern)
  - [ ] `execute()` en `executeStreaming()`: roept Codex CLI aan
  - [ ] `parseStreamResult()`: vertaalt Codex NDJSON events (`item.completed`, `thread.started`, `turn.completed`) naar intern format `{text, session_id, metadata}`
  - [ ] Geregistreerd in `yii/config/main.php` als `'aiProvider.codex'`

### FR-8: Frontend event abstractie voor real-time streaming

- Beschrijving: De frontend krijgt een event router die per provider de juiste handler aanroept voor streaming events.
- Acceptatiecriteria:
  - [ ] `onStreamEvent()` dispatcht naar provider-specifieke handler op basis van `activeProvider`
  - [ ] Claude handlers: ongewijzigd (`onStreamSystem`, `onStreamAssistant`, `onStreamResult`)
  - [ ] Codex handlers: `onCodexItemCompleted()` toont streaming tekst en tool-use, `onCodexTurnCompleted()` toont usage
  - [ ] Tool-use display is provider-aware: Claude tools (Read/Edit/Bash) vs Codex tools (shell/apply_patch)
  - [ ] Fallback: onbekende event types worden stilletjes genegeerd
  - [ ] Config check badge wordt provider-aware: toont `CLAUDE.md` of `codex.md` afhankelijk van actieve provider

### B-1: RunAiJob refactor — stream parsing delegeren aan provider

- Beschrijving: `RunAiJob` delegeert resultaat- en metadata-extractie aan de provider via `parseStreamResult()`, in plaats van zelf Claude-specifiek NDJSON te parsen.
- Acceptatiecriteria:
  - [ ] Streaming path: `$provider->parseStreamResult($streamLog)` → `markCompleted()`
  - [ ] Sync path: direct mapping van `execute()` resultaat → `markCompleted()` (geen NDJSON event nodig)
  - [ ] Drie private methoden verwijderd: `extractResultText()`, `extractMetadata()`, `extractSessionId()`
  - [ ] `ClaudeCliProvider::parseStreamResult()` ongewijzigd (bevat al juiste logica)
  - [ ] Sync fallback schrijft provider-agnostisch `{"type":"sync_result","text":"..."}` event
  - [ ] Bestaande tests in `RunAiJobTest` aangepast voor nieuwe flow
  - [ ] Alle bestaande unit tests blijven groen

## Gebruikersflow

### Project configuratie
1. Gebruiker opent project-instellingen (edit form)
2. Tabs tonen per geregistreerde provider (bijv. "Claude", "Codex")
3. Gebruiker selecteert Claude-tab → ziet model, permission mode, allowed/disallowed tools, system prompt, command dropdown
4. Gebruiker selecteert Codex-tab → ziet model, approval mode
5. Gebruiker slaat project op → opties worden genamespaced opgeslagen in `ai_options` JSON

### Chat sessie
1. Gebruiker opent AI chat voor een project
2. Provider dropdown toont beschikbare providers (Claude, Codex)
3. Gebruiker selecteert Codex → model en custom velden wisselen dynamisch
4. Gebruiker stuurt prompt → request bevat `provider: "codex"` + Codex-specifieke opties
5. Backend merged project-defaults met runtime-selectie
6. `RunAiJob` resolved Codex provider, roept `executeStreaming()` aan
7. Frontend event router dispatcht Codex events naar Codex handlers
8. Streaming tekst wordt real-time getoond

### Nieuwe provider toevoegen (developer flow)
1. Implementeer provider-klasse (minimaal `AiProviderInterface`)
2. Registreer in `yii/config/main.php` als `'aiProvider.{id}'`
3. Voeg toe aan `AiProviderRegistry` constructor array
4. Klaar — provider werkt in chat, met of zonder configuratie/streaming/workspace

## Edge cases

| Case | Gedrag |
|------|--------|
| Project heeft legacy flat `ai_options` | Bij lezen: automatisch herkend als flat, teruggegeven voor default provider. Bij eerste save: gemigreerd naar genamespaced structuur. |
| Provider verwijderd uit registry maar project heeft opties | Opties blijven in JSON, worden genegeerd. Geen tab getoond. Geen data verloren. |
| Cross-provider session resume | `prepareRunRequest()` reset `sessionId` naar `null` als provider verschilt van eerste run in sessie (bestaand gedrag). |
| Provider zonder `AiConfigProviderInterface` | Geen tab in project form, geen model/permission keuze. Werkt alleen via chat met defaults. |
| Provider zonder `AiStreamingProviderInterface` | Sync fallback in `RunAiJob` — direct `execute()` resultaat, geen stream parsing nodig. |
| Provider zonder `AiWorkspaceProviderInterface` | Geen workspace sync bij project save. Provider draait vanuit project root of managed fallback. |
| Codex `approval-mode` niet gezet | Codex CLI gebruikt eigen default (suggest). Geen PromptManager interventie. |
| Codex CLI niet geïnstalleerd | Provider registratie slaagt (PHP klasse laadt). `execute()` faalt met "command not found" → `AiRun` marked failed. |
| Workspace migratie `{id}/` → `{id}/claude/` | Bij eerste access: als `{id}/CLAUDE.md` bestaat maar `{id}/claude/` niet, verplaats bestanden. |
| Lege `getSupportedPermissionModes()` | Permission mode dropdown wordt niet getoond in tab (Codex case). |
| Onbekende event types in NDJSON stream | Frontend negeert stilletjes (fallback in event router). |

## Entiteiten en relaties

### Bestaande entiteiten
- **Project** (`yii/models/Project.php`) — `ai_options` kolom wordt genamespaced per provider; nieuwe methoden: `getAiOptionsForProvider()`, `setAiOptionsForProvider()`, `getDefaultProvider()`, `isNamespacedOptions()`
- **AiRun** (`yii/models/AiRun.php`) — `provider` kolom al aanwezig, `options` kolom bevat provider-specifieke opties (ongewijzigd)
- **AiProviderRegistry** (`yii/services/ai/AiProviderRegistry.php`) — ongewijzigd, al klaar voor meerdere providers
- **AiConfigProviderInterface** (`yii/services/ai/AiConfigProviderInterface.php`) — uitgebreid met `getConfigSchema()`

### Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| `AiConfigProviderInterface` | Interface | `yii/services/ai/AiConfigProviderInterface.php` | Wijzigen: `getConfigSchema()` methode toevoegen |
| `ClaudeCliProvider` | Service | `yii/services/ai/providers/ClaudeCliProvider.php` | Wijzigen: `getConfigSchema()` implementeren, workspace path → `{id}/claude/` |
| `CodexCliProvider` | Service | `yii/services/ai/providers/CodexCliProvider.php` | Nieuw: tweede provider implementatie |
| `Project` | Model | `yii/models/Project.php` | Wijzigen: `getAiOptionsForProvider()`, `setAiOptionsForProvider()`, `getDefaultProvider()`, `isNamespacedOptions()`, `afterSave()` + `afterDelete()` provider-aware, `getAiCommandBlacklist()` + `getAiCommandGroups()` optionele provider parameter |
| `ProjectController` | Controller | `yii/controllers/ProjectController.php` | Wijzigen: constructor DI → `AiProviderRegistry`, `loadAiOptions()` per-provider opslaan, `actionUpdate()`/`actionCreate()` registry doorgeven aan view, `actionAiCommands()` provider parameter, `projectConfigStatus` provider-aware |
| `AiChatController` | Controller | `yii/controllers/AiChatController.php` | Wijzigen: `prepareRunRequest()` whitelist verwijderen + per-provider defaults, `buildProviderData()` configSchema toevoegen |
| `RunAiJob` | Job | `yii/jobs/RunAiJob.php` | Wijzigen: stream parsing delegeren aan provider, extract-methoden verwijderen |
| `_form.php` (project) | View | `yii/views/project/_form.php` | Wijzigen: hardcoded Claude card → dynamische per-provider tabs |
| `index.php` (ai-chat) | View | `yii/views/ai-chat/index.php` | Wijzigen: configSchema rendering, event router, provider-aware config badge |
| `docker-compose.yml` | Config | `docker-compose.yml` | Wijzigen: `~/.codex/` mount op `pma_yii` en `pma_queue` |
| `Dockerfile` | Config | `docker/Dockerfile` | Wijzigen: `npm install -g @openai/codex` |
| `main.php` | Config | `yii/config/main.php` | Wijzigen: `CodexCliProvider` registratie als singleton + toevoegen aan registry |

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| `AiProviderRegistry` | `yii/services/ai/AiProviderRegistry.php` | Ongewijzigd — accepteert al meerdere providers |
| `AiProviderInterface` + sub-interfaces | `yii/services/ai/` | Ongewijzigd (behalve `AiConfigProviderInterface`) — nieuwe providers implementeren dezelfde interfaces |
| Provider dropdown in chat view | `yii/views/ai-chat/index.php:126-133` | Al aanwezig, conditionally getoond bij >1 provider |
| `repopulateSelect()` JS functie | `yii/views/ai-chat/index.php:875-881` | Hergebruikt voor dynamische custom velden bij provider-wissel |
| `buildProviderData()` | `yii/controllers/AiChatController.php:1187-1213` | Uitgebreid met `configSchema` key |
| `prepareRunRequest()` | `yii/controllers/AiChatController.php:790-865` | Aangepast voor per-provider defaults |
| `createRun()` | `yii/controllers/AiChatController.php:872-893` | Ongewijzigd — slaat provider identifier al op |
| `ClaudeCliProvider::parseStreamResult()` | `yii/services/ai/providers/ClaudeCliProvider.php:353-400` | Ongewijzigd — al correct geïmplementeerd |
| Alpine.js state management | `yii/views/ai-chat/index.php` | Hergebruikt voor custom velden state |
| Bootstrap 5 tabs | Framework | Hergebruikt voor per-provider tabs in project form |
| `proc_open()` pattern | `ClaudeCliProvider.php:54-179` | Hergebruikt als basis voor `CodexCliProvider::execute()` |

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| `getConfigSchema()` op interface i.p.v. aparte schema-klassen | Minimale aanpassing, schema leeft bij de provider die het kent. Geen extra abstractie nodig. |
| Genamespaced `ai_options` i.p.v. aparte tabel | Geen migratie nodig, backward-compatible, JSON kolom is flexibel genoeg voor variabele provider-opties. |
| Tabs i.p.v. accordion voor project form | Betere UX bij meerdere providers — directe zichtbaarheid, geen scroll nodig. Bij 1 provider: geen tabs (huidige UX). |
| `allowedKeys` whitelist verwijderen | Provider bepaalt zelf welke keys relevant zijn. Whitelist blokkeert provider-specifieke opties. |
| Stream parsing delegeren aan provider (B-1) | Elimineert Claude-specifieke logica uit `RunAiJob`. Provider kent eigen event format. Prerequisite voor Codex. |
| Raw NDJSON events in stream-file (B-2) | Frontend kan provider-specifieke info tonen zonder backend-wijzigingen. SSE relay is al format-agnostisch. |
| Workspace path `{id}/{provider_id}/` | Voorkomt conflicten tussen provider config files (CLAUDE.md vs codex.md). Duidelijke scheiding. |
| Codex zonder `AiWorkspaceProviderInterface` initieel | Codex workspace management kan later toegevoegd worden. Minimale eerste implementatie. |
| `getDefaultProvider()` hardcoded fallback `'claude'` i.p.v. registry lookup | Model mag niet afhankelijk zijn van services (DI anti-pattern). `_default` key in JSON lost dit op; fallback is statisch. |
| `afterSave()` resolved registry via `Yii::$container` | Bestaand patroon in codebase (huidige code doet `Yii::$container->get(AiProviderInterface::class)`). Model-naar-service koppeling is onvermijdelijk voor Yii2 event hooks. |

## Open vragen

Geen.

## UI/UX overwegingen

### Layout/Wireframe — Project form (meerdere providers)

```
┌─────────────────────────────────────────────────────┐
│ Project: My App                                     │
├─────────────────────────────────────────────────────┤
│ Name:           [My App_______________]             │
│ Label:          [myapp_________________]            │
│ Color Scheme:   [No color scheme ▼]                 │
│ Root Directory: [/path/to/project______]            │
│ Blacklisted:    [vendor,runtime________]            │
│ Extensions:     [php,js,md_____________]            │
│ Copy Format:    [Markdown ▼]                        │
│                                                     │
│ ┌──────────┐┌──────────┐┌──────────┐               │
│ │ Claude   ││ Codex    ││ Context  │               │
│ └──────────┘└──────────┘└──────────┘               │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Model:          [Sonnet ▼]                      │ │
│ │ Permission:     [Plan ▼]                        │ │
│ │ Allowed Tools:  [Read,Glob__________]           │ │
│ │ Disallowed:     [Bash_______________]           │ │
│ │ System Prompt:  [_______________________]       │ │
│ │                 [_______________________]       │ │
│ │                                                 │ │
│ │ ▼ Command Dropdown                              │ │
│ │ ┌─────────────────────────────────────────────┐ │ │
│ │ │ (blacklist/groups config)                   │ │ │
│ │ └─────────────────────────────────────────────┘ │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ Linked Projects: [Select2 multi-select]             │
│ Description:     [Quill editor]                     │
│                                                     │
│                          [Cancel] [Save]            │
└─────────────────────────────────────────────────────┘
```

### Layout/Wireframe — Project form (meerdere providers — tab behavior)

- Default actieve tab: de provider die overeenkomt met `$project->getDefaultProvider()` (of eerste tab bij nieuw project)
- Tab volgorde: registratievolgorde in `AiProviderRegistry` → Context tab altijd als laatste
- Tab state persistent: bij form validation error blijft de laatst actieve tab open

### Layout/Wireframe — Project form (enkele provider)

```
┌─────────────────────────────────────────────────────┐
│ ┌─────────────────────────────────────────────────┐ │
│ │ ▸ Claude CLI Defaults                           │ │
│ │   (collapsible card, zelfde als huidige UX)     │ │
│ │   Model:    [Sonnet ▼]                          │ │
│ │   ...velden uit getConfigSchema()...            │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────┐ │
│ │ ▸ Claude Command Dropdown                       │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────┐ │
│ │ ▸ Project Context                               │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```
Card header toont `"{provider_name} CLI Defaults"` (dynamisch, niet hardcoded "Claude").

### Layout/Wireframe — Chat view settings panel

```
┌─────────────────────────────────┐
│ Provider:  [Claude ▼]           │
│ Model:     [Sonnet ▼]           │
│ Permission:[Plan ▼]             │
│ ─── Provider Options ────────── │
│ Allowed:   [Read,Glob_________] │
│ Disallowed:[Bash______________] │
│ System:    [__________________] │
└─────────────────────────────────┘

Bij switch naar Codex:
┌─────────────────────────────────┐
│ Provider:  [Codex ▼]            │
│ Model:     [codex-mini ▼]       │
│ ─── Provider Options ────────── │
│ Approval:  [Auto Edit ▼]       │
└─────────────────────────────────┘
```

### UI States

| State | Visueel |
|-------|---------|
| Loading (project form) | Tab content laadt met spinner bij command dropdown fetch |
| Empty (geen providers) | Onmogelijk — minimaal 1 provider vereist door `AiProviderRegistry` constructor |
| Empty (provider zonder config) | Geen tab getoond voor die provider; provider werkt via chat met defaults |
| Error (CLI not found) | `AiRun` status → failed, error message getoond in chat: "Command not found: codex" |
| Error (auth expired) | CLI retourneert auth error → `AiRun` marked failed, error in chat |
| Success (provider switch) | Model/permission dropdowns animeren met fade-transition bij provider wissel |
| Success (save project) | Alle provider-tabs opties opgeslagen, success flash message |
| Single provider | Geen tabs, directe card weergave (huidige UX behouden) |

### Accessibility

- Tab navigation via `role="tablist"` / `role="tab"` / `role="tabpanel"` + `aria-selected`
- Provider dropdown in chat: `aria-label="Select AI provider"`
- Custom velden uit `getConfigSchema()`: `aria-describedby` linkt naar hint text
- Keyboard: Arrow keys navigeert tussen provider tabs (Bootstrap 5 tabs standaard), Tab navigeert naar tab content
- Screen reader: Provider naam als tab label, veldlabels gekoppeld via `for`/`id`
- Motion sensitivity: provider switch in chat gebruikt `prefers-reduced-motion` media query — geen fade-transition als gebruiker dit prefereert

## Technische overwegingen

### Backend

**Interface uitbreiding:**
```php
// AiConfigProviderInterface — nieuw methode
public function getConfigSchema(): array;
// Return: array<string, array{type, label, hint?, placeholder?, options?, default?, group?}>
```

**Project model uitbreiding:**
```php
// Nieuwe methoden op Project
public function getAiOptionsForProvider(string $identifier): array;
public function setAiOptionsForProvider(string $identifier, array $options): void;
public function getDefaultProvider(): string;  // Fallback: 'claude' (geen registry dependency)
private function isNamespacedOptions(array $options): bool;
// Heuristic: options is namespaced als minimaal 1 waarde een array is
// en de key een geldige provider identifier is (lowercase alphanum+dash).
// Flat options hebben altijd scalar values (string, int, null).
```

**ProjectController wijziging — `loadAiOptions()`:**
```php
// Huidige implementatie:
private function loadAiOptions(Project $model): void {
    $aiOptions = Yii::$app->request->post('ai_options', []);
    $model->setAiOptions($aiOptions);
}

// Wordt:
private function loadAiOptions(Project $model): void {
    $aiOptions = Yii::$app->request->post('ai_options', []);
    foreach ($aiOptions as $providerId => $providerOptions) {
        if (is_array($providerOptions) && $this->providerRegistry->has($providerId))
            $model->setAiOptionsForProvider($providerId, $providerOptions);
    }
}
```

**Controller wijzigingen:**
- `ProjectController::actionUpdate()` — verwerkt `ai_options[claude][...]`, `ai_options[codex][...]` form data
- `AiChatController::prepareRunRequest()` — verwijdert `allowedKeys`, leest per-provider defaults
- `AiChatController::buildProviderData()` — voegt `configSchema` toe

**RunAiJob refactor:**
- Streaming path: `$provider->parseStreamResult($streamLog)` vervangt drie private extract-methoden
- Sync path: direct mapping `execute()` → `markCompleted()` zonder NDJSON event
- Sync fallback schrijft `{"type":"sync_result","text":"..."}` event voor SSE relay

**Provider registratie:**
```php
// yii/config/main.php
'singletons' => [
    'aiProvider.claude' => ClaudeCliProvider::class,
    'aiProvider.codex' => CodexCliProvider::class,
    AiProviderRegistry::class => function () {
        return new AiProviderRegistry([
            Yii::$container->get('aiProvider.claude'),
            Yii::$container->get('aiProvider.codex'),
        ]);
    },
],
'definitions' => [
    // Bestaande binding behouden voor backward-compat (model afterSave/afterDelete)
    AiProviderInterface::class => function () {
        return Yii::$container->get(AiProviderRegistry::class)->getDefault();
    },
],
```

**Codex CLI command structuur:**
```bash
# Non-interactief met streaming
codex exec --json --approval-mode auto-edit --model codex-mini -p -

# Resume sessie
codex exec resume <session-id> --json -p -
```

### Frontend

**Chat view JS — event router:**
```javascript
// Meta event types (provider-agnostisch) — verwerkt VOOR provider dispatch:
// 'waiting', 'keepalive', 'prompt_markdown', 'run_status', 'server_error', 'sync_result'

// Provider-specifieke event handlers
const eventHandlers = {
    claude: {
        onEvent(data) {
            if (data.type === 'system' && data.subtype === 'init') self.onStreamInit(data);
            else if (data.type === 'stream_event') self.onStreamDelta(data.event);
            else if (data.type === 'assistant' && !data.isSidechain) self.onStreamAssistant(data);
            else if (data.type === 'result') self.onStreamResult(data);
        }
    },
    codex: {
        onEvent(data) {
            if (data.type === 'thread.started') self.onCodexThreadStarted(data);
            else if (data.type === 'item.completed') self.onCodexItemCompleted(data);
            else if (data.type === 'turn.completed') self.onCodexTurnCompleted(data);
            else if (data.type === 'error') self.onStreamError(data.message || 'Codex error');
        }
    }
};

// Dispatch in onStreamEvent() — na meta event handling:
const handler = eventHandlers[self.activeProvider];
if (handler) handler.onEvent(data);
// Else: silently ignore
```

**Chat view JS — `getOptions()` uitbreiding:**
```javascript
getOptions: function() {
    var providerEl = document.getElementById('ai-provider');
    var options = {
        provider: providerEl ? providerEl.value : this.defaultProvider,
        model: document.getElementById('ai-model').value,
        permissionMode: document.getElementById('ai-permission-mode').value
    };
    // Voeg custom velden toe uit dynamisch gerenderde provider options
    var customFields = document.querySelectorAll('#provider-custom-fields [data-option-key]');
    customFields.forEach(function(el) {
        var key = el.dataset.optionKey;
        options[key] = el.type === 'checkbox' ? el.checked : el.value;
    });
    return options;
}
```

**Chat view JS — `prefillFromDefaults()` per-provider:**
```javascript
prefillFromDefaults: function() {
    var provider = this.getOptions().provider;
    var defaults = this.projectDefaults[provider] || {};
    // Prefill model, permissionMode, en custom velden uit provider-specifieke defaults
}
```

**Chat view JS — dynamic custom fields:**
```javascript
// Bij provider-wissel
function renderProviderOptions(providerData) {
    const schema = providerData.configSchema || {};
    const container = document.getElementById('provider-custom-fields');
    container.innerHTML = '';
    for (const [key, field] of Object.entries(schema)) {
        // Render select/text/textarea/checkbox op basis van field.type
        // Elk element krijgt data-option-key="{key}" attribuut voor getOptions()
    }
}
```

**Project form — dynamische tab rendering (PHP):**
```php
// Per provider die AiConfigProviderInterface implementeert:
// 1. Tab header met $provider->getName()
// 2. Model dropdown uit $provider->getSupportedModels()
// 3. Permission mode dropdown uit $provider->getSupportedPermissionModes() — lege array → dropdown niet getoond
// 4. Custom velden uit $provider->getConfigSchema():
//    - 'select' → Html::dropDownList() met options
//    - 'text' → Html::textInput()
//    - 'textarea' → Html::textarea()
//    - 'checkbox' → Html::checkbox()
//    - Alle labels/hints via Html::encode()
// 5. Command dropdown (als provider loadCommands() ondersteunt)
//    — AJAX fetch per tab bij eerste activatie (lazy-load, niet eager op page load)
//    — `actionAiCommands(id, provider)` AJAX endpoint met provider parameter
```

## Test scenarios

### Unit tests

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| `testGetAiOptionsForProviderReturnsNamespaced` | `ai_options = '{"claude":{"model":"sonnet"}}'`, identifier `'claude'` | `['model' => 'sonnet']` |
| `testGetAiOptionsForProviderReturnsEmptyForUnknown` | `ai_options = '{"claude":{"model":"sonnet"}}'`, identifier `'codex'` | `[]` |
| `testGetAiOptionsForProviderHandlesLegacyFlat` | `ai_options = '{"model":"sonnet"}'`, identifier `'claude'` (default) | `['model' => 'sonnet']` |
| `testSetAiOptionsForProviderNamespaces` | `setAiOptionsForProvider('codex', ['model' => 'codex-mini'])` | JSON bevat `{"codex":{"model":"codex-mini"}}` |
| `testSetAiOptionsForProviderPreservesOtherProviders` | Set codex opties, bestaande claude opties | Beide providers' opties aanwezig in JSON |
| `testGetDefaultProviderReturnsStoredDefault` | `ai_options = '{"_default":"codex"}'` | `'codex'` |
| `testGetDefaultProviderFallsBackToHardcodedDefault` | `ai_options = '{}'` (geen `_default` key) | `'claude'` (hardcoded fallback) |
| `testIsNamespacedOptionsDetectsFlat` | `{'model':'sonnet','permissionMode':'plan'}` | `false` |
| `testIsNamespacedOptionsDetectsNamespaced` | `{'claude':{'model':'sonnet'}}` | `true` |
| `testGetAiOptionsBackwardCompatible` | Legacy flat opties | Retourneert flat opties als default provider opties |
| `testClaudeGetConfigSchemaReturnsExpectedFields` | `ClaudeCliProvider::getConfigSchema()` | Array met keys `allowedTools`, `disallowedTools`, `appendSystemPrompt` |
| `testCodexGetConfigSchemaReturnsApprovalMode` | `CodexCliProvider::getConfigSchema()` | Array met key `approvalMode` met type `select` |
| `testCodexBuildCommandTranslatesApprovalMode` | `options = ['approvalMode' => 'auto-edit']` | Command bevat `--approval-mode auto-edit` |
| `testCodexBuildCommandOmitsEmptyApprovalMode` | `options = ['approvalMode' => '']` | Command bevat geen `--approval-mode` flag |
| `testCodexParseStreamResultExtractsText` | NDJSON met `item.completed` + `agent_message` | `['text' => '...', 'session_id' => '...', 'metadata' => [...]]` |
| `testCodexParseStreamResultExtractsSessionId` | NDJSON met `thread.started` event | `session_id` uit `thread_id` veld |
| `testCodexParseStreamResultExtractsUsage` | NDJSON met `turn.completed` + `usage` object | `metadata` bevat usage info |
| `testRunAiJobDelegatesStreamParsingToProvider` | Streaming run met mock provider | `parseStreamResult()` aangeroepen, extract-methoden niet |
| `testRunAiJobSyncFallbackSkipsParseStreamResult` | Sync run met non-streaming provider | `execute()` resultaat direct naar `markCompleted()` |
| `testRunAiJobSyncFallbackWritesSyncResultEvent` | Sync run | Stream file bevat `{"type":"sync_result","text":"..."}` |
| `testPrepareRunRequestUsesPerProviderDefaults` | Project met genamespaced opties, provider=codex | Codex defaults gemerged, niet Claude defaults |
| `testSetAiOptionsForProviderRemovesEmptyValues` | `setAiOptionsForProvider('claude', ['model' => '', 'permissionMode' => 'plan'])` | Alleen `permissionMode` opgeslagen, lege `model` key gefilterd |
| `testLoadAiOptionsIgnoresUnknownProviderKeys` | POST data met `ai_options[evil_provider][...]` | Onbekende provider key genegeerd, niet opgeslagen |
| `testBuildProviderDataIncludesConfigSchema` | Provider met `getConfigSchema()` resultaat | `providerData` bevat `configSchema` key met schema array |
| `testCodexParseStreamResultHandlesEmptyStream` | Lege of `null` stream log | `['text' => '', 'session_id' => null, 'metadata' => []]` |
| `testActionAiCommandsWithProviderParameter` | `actionAiCommands(id, provider: 'codex')` met mock Codex provider | Commands voor Codex geretourneerd |
| `testActionAiCommandsWithUnknownProviderFallsBack` | `actionAiCommands(id, provider: 'unknown')` | Commands voor default provider geretourneerd |
| `testGetAiCommandBlacklistWithProviderParameter` | Genamespaced opties, `getAiCommandBlacklist('codex')` | Blacklist uit Codex namespace |
| `testGetAiCommandGroupsDefaultsToDefaultProvider` | Genamespaced opties, `getAiCommandGroups()` (geen parameter) | Groups uit default provider namespace |
| `testAfterDeleteIteratesAllWorkspaceProviders` | Project met 2 workspace providers | `deleteWorkspace()` aangeroepen op beide providers |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| `testLegacyFlatOptionsMigratedOnSave` | Project met flat `ai_options`, save via new form | Opties genamespaced onder default provider key |
| `testProviderRemovedFromRegistryOptionsPreserved` | Provider verwijderd, project geladen | Geen crash, opties in JSON genegeerd, geen tab |
| `testCrossProviderSessionResetsContinuity` | Session gestart met Claude, switch naar Codex | `sessionId` reset naar `null` |
| `testProviderWithoutConfigNoTab` | Provider implementeert alleen `AiProviderInterface` | Geen tab in project form |
| `testProviderWithEmptyPermissionModesHidesDropdown` | `getSupportedPermissionModes()` retourneert `[]` | Permission mode dropdown niet getoond in tab/chat |
| `testCodexCliNotInstalledRunFails` | `codex` binary niet gevonden | `AiRun` marked failed met error message |
| `testWorkspaceMigrationMovesFiles` | `storage/projects/1/CLAUDE.md` bestaat, `storage/projects/1/claude/` niet | Bestanden verplaatst naar `1/claude/` |
| `testUnknownStreamEventsSilentlyIgnored` | Frontend ontvangt onbekend event type | Geen JS error, event genegeerd |
| `testSyncFallbackWorksForNonStreamingProvider` | Provider zonder `AiStreamingProviderInterface` | `execute()` aangeroepen, resultaat correct opgeslagen |

### Regressie-impact

Bestaande tests die mogelijk aangepast moeten worden:

| Test | Locatie | Reden |
|------|---------|-------|
| `RunAiJobTest` | `yii/tests/unit/jobs/RunAiJobTest.php` | B-1 refactor: extract-methoden verwijderd, streaming/sync path gewijzigd. Tests moeten mock provider met `parseStreamResult()` gebruiken. |
| `ProjectTest` (indien aanwezig) | `yii/tests/unit/models/ProjectTest.php` | `getAiOptions()` en `setAiOptions()` backward-compatible, maar nieuwe namespaced methoden vereisen nieuwe tests. |
| `AiChatControllerTest` (indien aanwezig) | `yii/tests/` | `prepareRunRequest()` whitelist verwijderd — tests die de whitelist valideren moeten aangepast worden. |
| `ClaudeCliProviderTest` | `yii/tests/unit/services/ai/providers/ClaudeCliProviderTest.php` | Workspace path wijzigt van `{id}` naar `{id}/claude/`. Tests met workspace path assertions moeten bijgewerkt worden. |
| `ProjectControllerTest` (indien aanwezig) | `yii/tests/` | Constructor injection wijzigt van `AiProviderInterface` naar `AiProviderRegistry`. Mock setup moet aangepast worden. `actionAiCommands()` heeft nieuwe `provider` parameter. |

## Implementatievolgorde

1. **FR-0**: Docker infra — `~/.codex/` mount + binary installatie
2. **FR-1**: `getConfigSchema()` op interface + `ClaudeCliProvider` implementatie
3. **FR-2**: Genamespaced opties opslag in `Project` model
4. **FR-3**: Per-provider tabs in project form
5. **FR-4**: Provider-specifieke opties doorstromen (`prepareRunRequest` + `buildCommand`)
6. **FR-5**: Chat view dynamische custom velden
7. **B-1**: `RunAiJob` refactor — parsing delegeren aan provider
8. **FR-8**: Frontend event abstractie — provider-specifieke event handlers
9. **FR-6**: Workspace management per provider
10. **FR-7**: `CodexCliProvider` als proof-of-concept

**Dependencies:**
- B-1 is prerequisite voor FR-7 (zonder gedelegeerde parsing kan Codex niet werken)
- FR-8 is prerequisite voor FR-7 (zonder frontend handlers toont Codex geen streaming output)
- FR-2 is prerequisite voor FR-3 en FR-4 (opslag moet klaar zijn voor form en runtime)

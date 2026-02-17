# Feature: AI Provider Abstraction Layer

> **IMPLEMENTATION WARNING — DO NOT implement this spec in a single session.**
> This spec describes ~70 file changes. A previous single-session attempt caused a CLI crash and left the app broken.
> Follow `plan.md` for the phased breakdown (11 phases). Read `impl/todos.md` before starting any work.
> See `impl/insights.md` for the root cause analysis.

## Samenvatting

Introduceer een provider-abstractielaag (gemeenschappelijk contract) waarmee nieuwe AI CLI providers (Codex, Gemini, Qwen) eenvoudig kunnen worden aangesloten. De abstractielaag definieert de minimale interface die elke provider moet implementeren. Specifieke implementaties — zoals Claude — mogen rijkere functionaliteit bieden bovenop dit gemeenschappelijke contract, maar sluiten altijd aan op de abstractielaag.

## User story

Als ontwikkelaar wil ik nieuwe AI CLI providers kunnen aansluiten door een interface te implementeren en in de DI-container te registreren, zodat het platform niet gebonden is aan één specifieke AI-provider en toekomstige providers eenvoudig toegevoegd kunnen worden.

## Kernprincipe

De abstractielaag is het **gemeenschappelijke minimum**. Elke provider implementeert deze interface. Specifieke providers (Claude, Codex, Gemini) mogen rijkere, provider-specifieke functionaliteit bieden bovenop het contract. De controller/view-laag werkt primair via de abstractie, maar kan provider-specifieke features aanbieden wanneer de actieve provider deze ondersteunt (via `instanceof`-checks of feature-flags op de provider).

## Functionele requirements

### FR-1: Provider Interface (gemeenschappelijk contract)

- Beschrijving: Het systeem definieert een `AiProviderInterface` als gemeenschappelijk minimum dat elke provider moet implementeren. Dit contract bevat alleen wat **alle** providers gemeenschappelijk hebben. Provider-specifieke features (workspace management, usage tracking, command loading) leven op de concrete implementatie — niet op de interface. Controllers mogen deze rijkere features aanspreken via `instanceof`-checks of optionele interfaces.
- Acceptatiecriteria:
  - [ ] Interface `AiProviderInterface` bestaat in `yii/services/ai/` met minimaal contract
  - [ ] Interface bevat alleen gemeenschappelijke methoden: execute, cancelProcess, getName, getIdentifier
  - [ ] Optionele interfaces voor rijkere features: `AiStreamingProviderInterface`, `AiWorkspaceProviderInterface`, `AiUsageProviderInterface`, `AiConfigProviderInterface`
  - [ ] Claude CLI provider implementeert de basis-interface + alle optionele interfaces
  - [ ] Een minimale provider (bijv. Codex) hoeft alleen de basis-interface te implementeren
  - [ ] DI-container registreert de actieve provider als `AiProviderInterface`

### FR-2: Provider-agnostische run-tracking

- Beschrijving: Het bestaande `claude_run`-model en bijbehorende database-tabel worden hernoemd naar een provider-onafhankelijke naam (`ai_run`), met een kolom die de gebruikte provider vastlegt.
- Acceptatiecriteria:
  - [ ] Database-tabel heet `ai_run` (via migratie van `claude_run`)
  - [ ] Kolom `provider` (string) toegevoegd om de gebruikte provider vast te leggen
  - [ ] Model heet `AiRun` met query class `AiRunQuery`
  - [ ] Enum heet `AiRunStatus` (zelfde waarden als `ClaudeRunStatus`)
  - [ ] Search model heet `AiRunSearch`
  - [ ] RBAC rule heet `AiRunOwnerRule`
  - [ ] Alle bestaande data migreert correct

### FR-3: Provider-agnostische project-configuratie

- Beschrijving: Project-kolommen `claude_options`, `claude_context`, `claude_permission_mode` worden hernoemd naar provider-onafhankelijke namen.
- Acceptatiecriteria:
  - [ ] Kolom `claude_options` → `ai_options` (via migratie)
  - [ ] Kolom `claude_context` → `ai_context` (via migratie)
  - [ ] Kolom `claude_permission_mode` → `ai_permission_mode` (via migratie)
  - [ ] Project model methods hernoemd (volledig):
    - `getClaudeOptions()` → `getAiOptions()`
    - `setClaudeOptions()` → `setAiOptions()`
    - `getClaudeOption()` → `getAiOption()`
    - `getClaudeCommandBlacklist()` → `getAiCommandBlacklist()`
    - `getClaudeCommandGroups()` → `getAiCommandGroups()`
    - `getClaudeContext()` → `getAiContext()`
    - `setClaudeContext()` → `setAiContext()`
    - `hasClaudeContext()` → `hasAiContext()`
    - `getClaudeContextAsMarkdown()` → `getAiContextAsMarkdown()`
  - [ ] Project attribute labels bijgewerkt ("Claude CLI Options" → "AI CLI Options", etc.)
  - [ ] Project `afterSave()`: `$relevantFields` array bijgewerkt (`'claude_context'` → `'ai_context'`, `'claude_options'` → `'ai_options'`)
  - [ ] Project `afterSave()`/`afterDelete()`: workspace calls via DI-resolutie `AiProviderInterface` + `instanceof AiWorkspaceProviderInterface` i.p.v. `Yii::$app->claudeWorkspaceService`
  - [ ] Bestaande data blijft behouden

### FR-4: Provider-agnostische controller en routes

- Beschrijving: De `ClaudeController` wordt hernoemd naar `AiChatController` met bijgewerkte routes. Oude routes redirecten naar nieuwe.
- Acceptatiecriteria:
  - [ ] Controller heet `AiChatController` in `yii/controllers/`
  - [ ] Routes veranderen van `/claude/*` naar `/ai/*`
  - [ ] URL aliassen of redirects voor backwards-compatibiliteit (optioneel)
  - [ ] Console controllers hernoemd: `AiRunController` (was `ClaudeRunController`)
  - [ ] RBAC config bijgewerkt naar nieuwe controller/action namen

### FR-5: Provider-agnostieke services

- Beschrijving: De Claude-specifieke services worden opgesplitst in een gemeenschappelijk contract (minimale interface + optionele interfaces) en een concrete Claude-implementatie die alle interfaces implementeert. Generieke services (relay, cleanup) worden hernoemd maar blijven provider-onafhankelijk.
- Acceptatiecriteria:
  - [ ] `AiProviderInterface` in `yii/services/ai/AiProviderInterface.php` (minimaal contract)
  - [ ] Optionele interfaces: `AiStreamingProviderInterface`, `AiWorkspaceProviderInterface`, `AiUsageProviderInterface`, `AiConfigProviderInterface`
  - [ ] `ClaudeCliProvider` implementeert alle interfaces (refactor van `ClaudeCliService` + `ClaudeWorkspaceService`)
  - [ ] `AiStreamRelayService` (was `ClaudeStreamRelayService`) — generiek, geen interface nodig
  - [ ] `AiRunCleanupService` (was `ClaudeRunCleanupService`) — werkt met `AiRun`
  - [ ] `RunAiJob` (was `RunClaudeJob`) — werkt via `AiProviderInterface`, checked optionele interfaces
  - [ ] `AiQuickHandler` (was `ClaudeQuickHandler`) — al provider-agnostisch via `AiCompletionClient`
  - [ ] Bestaande `AiCompletionClient` interface blijft behouden

### FR-6: Provider-specifieke permissie-modes

- Beschrijving: `ClaudePermissionMode` wordt hernoemd naar `AiPermissionMode`. De huidige enum-waarden (plan, dontAsk, bypassPermissions, acceptEdits, default) worden het generieke basisset. Providers die modes niet ondersteunen mogen onbekende waarden negeren. Providers die extra modes nodig hebben, kunnen deze toevoegen aan de enum.
- Acceptatiecriteria:
  - [ ] Enum heet `AiPermissionMode` in `yii/common/enums/`
  - [ ] Waarden blijven hetzelfde als basisset (plan, dontAsk, bypassPermissions, acceptEdits, default)
  - [ ] Provider-implementatie vertaalt enum naar provider-specifieke CLI flags
  - [ ] Provider mag onbekende modes negeren (graceful degradation)
  - [ ] UI toont alleen modes die de actieve provider rapporteert als ondersteund (via `AiConfigProviderInterface::getSupportedPermissionModes(): array`)

### FR-7: UI labels configureerbaar

- Beschrijving: Hardcoded "Claude" labels in views worden vervangen door een configureerbare providernaam, zodat de UI automatisch de naam van de actieve provider toont.
- Acceptatiecriteria:
  - [ ] Provider display naam komt primair van `AiProviderInterface::getName()` (dynamisch)
  - [ ] Fallback: `Yii::$app->params['aiProviderLabel']` (default: "AI") voor contexten zonder provider-instantie
  - [ ] Navigatie-items, page titles en button labels gebruiken de provider naam
  - [ ] Provider icon: `bi-terminal-fill` als default; provider mag icon overschrijven via optionele `getIcon(): string` methode op de interface
  - [ ] CSS-klassen worden NIET hernoemd (te grote impact, geen functionele noodzaak)
  - [ ] View directory wordt hernoemd van `views/claude/` naar `views/ai-chat/`

## Gebruikersflow

1. Administrator configureert de gewenste AI-provider in de DI-container (`config/main.php`)
2. Het systeem laadt de geregistreerde `AiProviderInterface` implementatie
3. Gebruiker opent het AI-chatscherm (route: `/ai/index`)
4. Gebruiker stelt een prompt samen en verstuurt deze
5. Het systeem checkt of de provider `AiStreamingProviderInterface` ondersteunt; zo ja, streamt via `executeStreaming()`, anders sync via `execute()`
6. De provider voert het commando uit en levert resultaten (streaming of sync)
7. Het systeem slaat het resultaat op als `AiRun` record met `provider` kolom
8. De gebruiker ziet de response in de chat-interface

## Edge cases

| Case | Gedrag |
|------|--------|
| Provider niet geconfigureerd | Fallback naar Claude CLI provider (default). Log warning. |
| Provider CLI binary niet gevonden | `AiRun` krijgt status `failed` met duidelijke foutmelding over ontbrekend binary |
| Migratie mislukt halverwege | `safeDown()` rolt alle wijzigingen terug via transactie |
| Bestaande `claude_run` records na migratie | Data migreert naar `ai_run` met `provider = 'claude'` |
| Provider-specifieke opties in project | `ai_options` JSON kolom bevat provider-specifieke keys; provider-implementatie valideert |
| Workspace configuratie verschilt per provider | `AiWorkspaceProviderInterface` (optioneel) delegeert naar provider-specifieke implementatie |
| Meerdere providers tegelijk actief | Scope: niet in v1. Eén actieve provider per applicatie-instantie |
| Legacy URLs (`/claude/*`) na rename | Yii2 URL rules kunnen aliassen definiëren voor backwards-compatibiliteit |
| sessionStorage keys (`claudePromptContent`) | Hernoemd naar `aiPromptContent` met fallback voor migratie (zowel lees- als schrijfzijde in integratie-views) |
| Project form field names (`claude_options[...]`) | Hernoemd naar `ai_options[...]` in `project/_form.php` + `loadAiOptions()` helper |
| HTML IDs in `project/_form.php` (`claudeOptionsCollapse`, etc.) | Behouden — interne referenties binnen dezelfde view, geen functionele noodzaak om te hernoemen |
| `AiRun::tableName()` | Retourneert `'{{%ai_run}}'` met tabel-prefix syntax (huidige `ClaudeRun` mist `{{%}}` wrapper) |
| Stream file opslag (`storage/claude-runs/`) | Directory hernoemd naar `storage/ai-runs/`; bestaande bestanden verplaatst via migratie of symlink |
| Provider-resolutie in RunAiJob | Job resolved `AiProviderInterface` uit DI-container (v1: één provider per instantie). Toekomstig: `provider` kolom op `AiRun` kan gebruikt worden voor multi-provider lookup |
| Queue job deserialisatie na rename | `class_alias('app\jobs\RunAiJob', 'app\jobs\RunClaudeJob')` in bootstrap (`web/index.php`, `yii` console entry) voorkomt dat bestaande geserialiseerde jobs in de queue tabel falen na de class rename |

## Entiteiten en relaties

### Bestaande entiteiten

- **Project** — bevat `ai_options` (was `claude_options`), `ai_context` (was `claude_context`), `ai_permission_mode` (was `claude_permission_mode`)
- **User** — eigenaar van AI runs
- **AiRun** (was ClaudeRun) — execution record met nieuw `provider` veld

### Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| `AiProviderInterface` | Interface | `yii/services/ai/AiProviderInterface.php` | Nieuw: minimaal provider contract |
| `AiStreamingProviderInterface` | Interface | `yii/services/ai/AiStreamingProviderInterface.php` | Nieuw: optioneel streaming contract |
| `AiWorkspaceProviderInterface` | Interface | `yii/services/ai/AiWorkspaceProviderInterface.php` | Nieuw: optioneel workspace contract |
| `AiUsageProviderInterface` | Interface | `yii/services/ai/AiUsageProviderInterface.php` | Nieuw: optioneel usage contract |
| `AiConfigProviderInterface` | Interface | `yii/services/ai/AiConfigProviderInterface.php` | Nieuw: optioneel config/commands contract |
| `ClaudeCliProvider` | Service | `yii/services/ai/providers/ClaudeCliProvider.php` | Nieuw: Claude implementatie van alle interfaces (refactor van ClaudeCliService + ClaudeWorkspaceService) |
| `AiRun` | Model | `yii/models/AiRun.php` | Wijzigen: hernoemd van ClaudeRun |
| `AiRunQuery` | Query | `yii/models/query/AiRunQuery.php` | Wijzigen: hernoemd van ClaudeRunQuery |
| `AiRunSearch` | Search | `yii/models/AiRunSearch.php` | Wijzigen: hernoemd van ClaudeRunSearch |
| `AiRunStatus` | Enum | `yii/common/enums/AiRunStatus.php` | Wijzigen: hernoemd van ClaudeRunStatus |
| `AiPermissionMode` | Enum | `yii/common/enums/AiPermissionMode.php` | Wijzigen: hernoemd van ClaudePermissionMode |
| `AiRunOwnerRule` | RBAC | `yii/rbac/AiRunOwnerRule.php` | Wijzigen: hernoemd van ClaudeRunOwnerRule |
| `AiChatController` | Controller | `yii/controllers/AiChatController.php` | Wijzigen: hernoemd van ClaudeController |
| `AiRunController` | Command | `yii/commands/AiRunController.php` | Wijzigen: hernoemd van ClaudeRunController (cleanup-stale, cleanup-files). `actionCleanupFiles` path hardcode bijwerken. |
| `AiController` | Command | `yii/commands/AiController.php` | Wijzigen: hernoemd van console ClaudeController (sync-workspaces, diagnose). Constructor DI-fallback bijwerken voor beide services. `actionDiagnose` binary-check delegeren naar provider. |
| `RunAiJob` | Job | `yii/jobs/RunAiJob.php` | Wijzigen: hernoemd van RunClaudeJob |
| `AiStreamRelayService` | Service | `yii/services/AiStreamRelayService.php` | Wijzigen: hernoemd van ClaudeStreamRelayService |
| `AiRunCleanupService` | Service | `yii/services/AiRunCleanupService.php` | Wijzigen: hernoemd van ClaudeRunCleanupService |
| `AiQuickHandler` | Handler | `yii/handlers/AiQuickHandler.php` | Wijzigen: hernoemd van ClaudeQuickHandler |
| `ClaudeCliCompletionClient` | Service | `yii/services/ClaudeCliCompletionClient.php` | Wijzigen: interne dependency naar ClaudeCliProvider |
| `Project` | Model | `yii/models/Project.php` | Wijzigen: kolommen en methods hernoemd |
| Views (16 bestanden) | Views | `yii/views/ai-chat/` + integratieviews | Wijzigen: directory rename, labels via parameter |
| Migratie: rename tabellen | Migration | `yii/migrations/m260216_000001_rename_claude_to_ai.php` | Nieuw: tabel/kolom rename |
| Migratie: add provider kolom | Migration | `yii/migrations/m260216_000002_add_provider_to_ai_run.php` | Nieuw: provider kolom |
| Config | Config | `yii/config/main.php` | Wijzigen: DI registraties updaten |
| RBAC config | Config | `yii/config/rbac.php` | Wijzigen: permission namen updaten |

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| `AiCompletionClient` interface | `yii/services/AiCompletionClient.php` | Blijft behouden als single-turn completion abstractie |
| `ClaudeQuickHandler` (→ `AiQuickHandler`) | `yii/handlers/ClaudeQuickHandler.php` | Al provider-agnostisch, enkel hernoemen |
| `ClaudeStreamRelayService` (→ `AiStreamRelayService`) | `yii/services/ClaudeStreamRelayService.php` | NDJSON relay is provider-agnostisch |
| RBAC owner rule patroon | `yii/rbac/ClaudeRunOwnerRule.php` | Zelfde patroon, enkel hernoemen |
| Service layer DI pattern | `yii/services/` | Constructor injection hergebruiken voor provider-injectie |
| Query class pattern | `yii/models/query/` | Chainable scopes hergebruiken voor AiRunQuery |
| TimestampTrait | `yii/models/traits/TimestampTrait.php` | Hergebruiken in AiRun model |

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| Minimale basis-interface + optionele feature-interfaces | Niet alle providers ondersteunen streaming, workspaces of usage. Basis-interface is het gemeenschappelijke minimum; rijkere features via optionele interfaces die providers naar keuze implementeren |
| `instanceof`-checks voor optionele features | Controllers checken of de provider een optionele interface implementeert; geen feature flags of configuratie nodig |
| Eén actieve provider per applicatie-instantie (v1) | Vereenvoudigt DI-configuratie en voorkomt provider-mixing in runs |
| CSS-klassen NIET hernoemen | 100+ CSS-klassen wijzigen is hoge impact zonder functioneel voordeel; interne naamgeving |
| Tabel rename via migratie i.p.v. nieuwe tabel | Behoudt alle bestaande data zonder complexe datamilgratie |
| `provider` kolom als string (niet enum) | Maakt het mogelijk om nieuwe providers toe te voegen zonder migratie. Validatie: `provider` moet voldoen aan `/^[a-z][a-z0-9-]{1,48}$/` (lowercase, alphanumeric + dash, max 50 chars) |
| View directory hernoemen naar `ai-chat/` | Consistentie met controller naam `AiChatController` |
| Workspace als optionele interface op provider | Niet alle providers hebben workspace-bestanden nodig; `AiWorkspaceProviderInterface` is optioneel en wordt via `instanceof` gedetecteerd |
| `AiProviderInterface` in `services/ai/` subdirectory | Scheidt abstracte interfaces van concrete services |
| Provider-implementaties in `services/ai/providers/` | Groeipunt voor toekomstige providers (Codex, Gemini) |
| sessionStorage key rename met fallback | Voorkomt verlies van in-progress prompts tijdens migratie |

## Open vragen

Geen

## UI/UX overwegingen

### Layout/Wireframe

De UI-structuur wijzigt niet. Enkel labels veranderen:

```
┌────────────────────────────────────────────────────┐
│  [Logo]  Projects  Contexts  Fields  {Provider}  ▼ │  ← "Claude" → AiProviderInterface::getName()
├────────────────────────────────────────────────────┤
│  {Provider} CLI                                    │  ← Page title
│  ┌──────────────────────────────────────────────┐  │
│  │ Settings  │  Usage                           │  │
│  ├──────────────────────────────────────────────┤  │
│  │ Prompt editor                                │  │
│  │ [Send] [New dialog]                          │  │
│  ├──────────────────────────────────────────────┤  │
│  │ Chat history                                 │  │
│  │ ┌─ User message ─────────────────────────┐   │  │
│  │ │ prompt text...                          │   │  │
│  │ └────────────────────────────────────────┘   │  │
│  │ ┌─ {Provider} response ──────────────────┐   │  │
│  │ │ response text...                        │   │  │
│  │ └────────────────────────────────────────┘   │  │
│  └──────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────┘
```

### UI States

| State | Visueel |
|-------|---------|
| Loading | Bestaand: "thinking dots" animatie — geen wijziging |
| Empty | Bestaand: lege chat met prompt editor — label past zich aan |
| Error | Bestaand: error message in stream modal — geen wijziging |
| Success | Bestaand: response in chat bubble — "Claude" label → provider label |
| Provider niet beschikbaar | Nieuw: Bootstrap warning alert bovenaan chat pagina: "AI provider niet geconfigureerd". Prompt editor disabled, bestaande chat history blijft leesbaar. |
| Streaming niet ondersteund | Provider zonder `AiStreamingProviderInterface`: prompt verzendt sync, spinner i.p.v. streaming dots, resultaat verschijnt na voltooiing |
| Feature niet ondersteund | UI-secties (Usage, Settings, Commands) worden verborgen als provider de bijbehorende optionele interface niet implementeert |

### Accessibility

- ARIA labels die "Claude" bevatten worden bijgewerkt naar dynamische providernaam
- Keyboard navigatie wijzigt niet
- Screen reader teksten worden bijgewerkt met generieke term of providernaam

## Technische overwegingen

### Backend

#### Interface-hiërarchie

```
yii/services/ai/
├── AiProviderInterface.php              # Minimaal contract (alle providers)
├── AiStreamingProviderInterface.php     # Optioneel: streaming support
├── AiWorkspaceProviderInterface.php     # Optioneel: workspace management
├── AiUsageProviderInterface.php         # Optioneel: usage/subscription tracking
├── AiConfigProviderInterface.php        # Optioneel: config check, command loading
└── providers/
    └── ClaudeCliProvider.php            # Claude: implementeert ALLE interfaces
```

**AiProviderInterface (verplicht — alle providers):**
- `execute(string $prompt, string $workDir, int $timeout, array $options, ?Project $project, ?string $sessionId): array`
- `cancelProcess(string $streamToken): bool`
- `getName(): string` — provider display name
- `getIdentifier(): string` — provider identifier voor DB opslag

> **Noot:** `convertToMarkdown()` is geen provider-verantwoordelijkheid. Quill Delta → Markdown conversie blijft bij `CopyFormatConverter` die al provider-agnostisch is.

**AiStreamingProviderInterface (optioneel):**
- `executeStreaming(string $prompt, string $workDir, callable $onLine, int $timeout, array $options, ?Project $project, ?string $sessionId, ?string $streamToken): array`
- `parseStreamResult(?string $streamLog): array` — parsed provider-specifiek stream-log (NDJSON) naar gestandaardiseerd resultaat (`['text' => string, 'session_id' => ?string, 'metadata' => array]`). Verplaatst Claude-specifieke parsers (`extractResultText`, `extractMetadata`, `extractSessionId`) uit `RunAiJob` naar de provider.

> **Noot:** De `$onLine` callback staat op positie 3 (niet achteraan) — dit volgt de bestaande `ClaudeCliService::executeStreaming()` signature. `$streamToken` is nodig voor cancel-support.

**AiWorkspaceProviderInterface (optioneel):**
- `ensureWorkspace(Project $project): string`
- `syncConfig(Project $project): void`
- `deleteWorkspace(Project $project): void`
- `getWorkspacePath(Project $project): string`
- `getDefaultWorkspacePath(): string`

> **Noot:** Workspace-specifieke configuratiegeneratie (bijv. `generateClaudeMd()`, `generateSettingsJson()`) zijn provider-specifiek en blijven als public methods op de concrete `ClaudeCliProvider`. Ze worden intern aangeroepen door `syncConfig()` en worden getest via unit tests op de concrete klasse.

**AiUsageProviderInterface (optioneel):**
- `getUsage(): array`

**AiConfigProviderInterface (optioneel):**
- `hasConfig(string $path): array` — retourneert diagnostische array (bijv. `['hasConfigFile' => bool, 'hasConfigDir' => bool, 'hasAnyConfig' => bool]`). Provider-specifieke keys zijn toegestaan.
- `checkConfig(string $path): array`
- `loadCommands(string $directory): array`
- `getSupportedPermissionModes(): array` — lijst van ondersteunde `AiPermissionMode` waarden

**Controller-patronen voor optionele features:**
```php
// Streaming: gebruik als provider het ondersteunt, anders sync fallback
if ($provider instanceof AiStreamingProviderInterface) {
    $result = $provider->executeStreaming(...);
} else {
    $result = $provider->execute(...);
}

// Workspace: alleen als provider het ondersteunt
if ($provider instanceof AiWorkspaceProviderInterface) {
    $provider->ensureWorkspace($project);
}
```

#### Implementatie-aandachtspunten

| Punt | Detail |
|------|--------|
| `RunAiJob` factory method | Job gebruikt `createCliService()` factory method voor testbaarheid — wijzig return type naar `AiProviderInterface`, resolve via `Yii::$container->get(AiProviderInterface::class)` |
| Console `AiController` fallback | Huidige console controller gebruikt `new ClaudeCliService()` als fallback — vervang door DI-resolutie |
| `ClaudeCliCompletionClient` dependency | Heeft hard dependency op `ClaudeCliService` — update naar `AiProviderInterface` type hint |
| Return type complexiteit | `execute()` retourneert complex associative array (15+ optional keys) — documenteer als PHPDoc op interface |
| Private helpers in ClaudeCliService | `translatePath()`, `buildCommand()`, `parseStreamJsonOutput()`, `storeProcessPid()`, `clearProcessPid()`, `determineWorkingDirectory()`, `parseJsonOutput()`, `extractModelInfo()`, `extractToolUses()`, `formatModelName()`, `parseCommandDescription()`, `fetchSubscriptionUsage()`, `normalizeUsageData()`, `resolveCredentialsPath()` — blijven op concrete klasse, geen interface nodig |
| `getGitBranch()` niet op interface | Wordt aangeroepen in `AiChatController::actionIndex`. Is geen provider-verantwoordelijkheid. Verplaats naar een utility of inline in controller (simpele `git branch --show-current` call) |
| `convertToMarkdown()` controller-dependency | Controller roept dit aan in `prepareClaudeRequest()`. Na refactor beschikbaar via `CopyFormatConverter` — injecteer als tweede constructor-dependency in `AiChatController` |
| `RunAiJob` stream parsing | `extractResultText()`, `extractMetadata()`, `extractSessionId()` bevatten Claude-specifieke NDJSON types (`type: result`, `type: system`). Verplaats deze parsers naar `ClaudeCliProvider` als `parseStreamResult(string $streamLog): array`. `RunAiJob` roept de provider's parser aan |
| `RunAiJob::createQuickHandler()` | Factory method retourneert `ClaudeQuickHandler` — update naar `AiQuickHandler` |
| Test files (14) | Tests instantiëren `ClaudeCliService` direct via `new` — update naar concrete class of mock via interface |

#### Migraties

**Migratie 1: Tabel/kolom rename**
```sql
RENAME TABLE {{%claude_run}} TO {{%ai_run}};
ALTER TABLE {{%ai_run}} ADD COLUMN provider VARCHAR(50) NOT NULL DEFAULT 'claude' AFTER project_id;
ALTER TABLE {{%project}} CHANGE claude_options ai_options JSON;
ALTER TABLE {{%project}} CHANGE claude_context ai_context LONGTEXT;
ALTER TABLE {{%project}} CHANGE claude_permission_mode ai_permission_mode VARCHAR(50);
-- Rename foreign keys and indexes
```

#### Queue configuratie (`config/main.php`)

```php
'queue' => [
    'channel' => 'ai',  // was: 'claude'
    // TTR blijft 3900s — voldoende voor alle CLI providers
],
```

> **Let op:** Bestaande jobs in de `queue` tabel met `channel = 'claude'` worden na migratie niet meer verwerkt. De migratie moet een `UPDATE {{%queue}} SET channel = 'ai' WHERE channel = 'claude'` bevatten, of de queue moet leeg gedraaid worden vóór deployment.

#### PID cache key prefix

- Huidige prefix: `'claude_cli_pid_'` → Nieuw: `'ai_cli_pid_'`
- Locatie: `ClaudeCliService::buildPidCacheKey()` → `ClaudeCliProvider::buildPidCacheKey()`

#### DI configuratie (`config/main.php`)

```php
'container' => [
    'definitions' => [
        AiProviderInterface::class => ClaudeCliProvider::class,
        AiCompletionClient::class => ClaudeCliCompletionClient::class,
    ],
],
// ClaudeCliProvider implementeert ook AiStreamingProviderInterface,
// AiWorkspaceProviderInterface, AiUsageProviderInterface, AiConfigProviderInterface.
// Controllers checken via instanceof of deze features beschikbaar zijn.
```

> **Let op:** DI configuratie moet ook in `config/console.php` en `config/test.php` worden bijgewerkt als deze eigen container definitions hebben. De bestaande `claudeWorkspaceService` component-registratie in `config/main.php` moet worden verwijderd. Workspace-functionaliteit leeft nu op `ClaudeCliProvider` (via `AiWorkspaceProviderInterface`). `Project::afterSave()` en `Project::afterDelete()` die `Yii::$app->claudeWorkspaceService` aanroepen moeten worden bijgewerkt naar DI-resolutie van `AiProviderInterface` + `instanceof AiWorkspaceProviderInterface` check.

#### Validatie/endpoints

| Endpoint (nieuw) | Methode | Validatie |
|-------------------|---------|-----------|
| `POST /ai/start-run` | AiChatController::actionStartRun | RBAC projectOwner, input sanitizatie |
| `GET /ai/stream-run` | AiChatController::actionStreamRun | RBAC aiRunOwner, SSE headers |
| `POST /ai/cancel-run` | AiChatController::actionCancelRun | RBAC aiRunOwner |
| `GET /ai/run-status` | AiChatController::actionRunStatus | RBAC aiRunOwner |
| `GET /ai/runs` | AiChatController::actionRuns | RBAC viewProject |
| `POST /ai/delete-session` | AiChatController::actionDeleteSession | RBAC aiRunOwner |
| `GET /ai/usage` | AiChatController::actionUsage | RBAC viewProject |
| `GET /ai/check-config` | AiChatController::actionCheckConfig | RBAC viewProject |
| `POST /ai/suggest-name` | AiChatController::actionSuggestName | RBAC viewProject, input sanitizatie |
| `GET /ai/index` | AiChatController::actionIndex | RBAC viewProject, project context required |
| `POST /ai/save` | AiChatController::actionSave | RBAC aiRunOwner |
| `POST /ai/cleanup` | AiChatController::actionCleanup | RBAC viewProject, confirmation |
| `GET /ai/active-runs` | AiChatController::actionActiveRuns | RBAC viewProject |
| `POST /ai/summarize-session` | AiChatController::actionSummarizeSession | RBAC viewProject |
| `POST /ai/summarize-prompt` | AiChatController::actionSummarizePrompt | RBAC viewProject |
| `POST /ai/summarize-response` | AiChatController::actionSummarizeResponse | RBAC viewProject |
| `POST /ai/stream` | AiChatController::actionStream | RBAC viewProject (legacy phase-1 SSE wrapper) |
| `POST /ai/cancel` | AiChatController::actionCancel | RBAC viewProject |
| `POST /ai/import-text` | AiChatController::actionImportText | RBAC viewProject (delegeert naar NoteController) |
| `POST /ai/import-markdown` | AiChatController::actionImportMarkdown | RBAC viewProject (delegeert naar NoteController) |

#### Integratie met andere controllers

| Controller | Huidige action | Nieuw | Wijziging |
|------------|---------------|-------|-----------|
| `ProjectController` | `actionClaudeCommands(int $id)` | `actionAiCommands(int $id)` | Route `/project/claude-commands` → `/project/ai-commands`. Laadt slash commands voor project. |
| `ProjectController` | `actionClaude(int $id)` (deprecated) | Verwijderen of redirect naar `/ai/index` | Deprecated redirect, kan verwijderd worden |
| `ProjectController` | `loadClaudeOptions()` (private) | `loadAiOptions()` | POST key `claude_options[...]` → `ai_options[...]` (form fields mee updaten) |
| `NoteController` | `actionClaude(int $id)` | `actionAi(int $id)` | Redirect naar `/ai/index` i.p.v. `/claude/index` |

### Frontend

#### JavaScript wijzigingen

- `yii/views/ai-chat/index.php` — Inline JS: URLs bijwerken van `/claude/*` naar `/ai/*`
- `sessionStorage` key: `claudePromptContent` → `aiPromptContent` (met fallback)
- `sessionStorage` key: `claude-runs-auto-refresh` → `ai-runs-auto-refresh` (met fallback)
- Geen aparte JS-module nodig; alles is inline in views
- Integratie-views (`note/view.php`, `prompt-instance/view.php`, etc.): URLs bijwerken van `/claude/index` → `/ai/index`
- Integratie-views die `claudePromptContent` SCHRIJVEN moeten ook key updaten naar `aiPromptContent`:
  - `views/prompt-instance/_form.php`
  - `views/prompt-instance/view.php`
  - `views/prompt-instance/index.php`
  - `views/note/view.php`
  - `views/note/index.php`
- `views/ai-chat/index.php`: hardcoded permission mode array vervangen door dynamische call naar `AiConfigProviderInterface::getSupportedPermissionModes()` (met fallback naar volledige set als provider interface niet implementeert)
- `views/ai-chat/index.php`: hardcoded JS strings updaten:
  - `'Claude thinking'` → `'{Provider} thinking'` (via PHP variabele in inline JS)
  - `'Claude ' + status` → `'{Provider} ' + status`
  - Role label `'Claude'` in chat bubbles → providernaam
  - Page title `'Claude CLI'` → `'{Provider} CLI'`
  - Breadcrumb `'Claude CLI'` → `'{Provider} CLI'`
- `views/ai-chat/runs.php`: page title `'Claude Sessions'` → `'{Provider} Sessions'`
- `views/ai-chat/cleanup-confirm.php`: breadcrumb `'Claude Sessions'` → `'{Provider} Sessions'`

**sessionStorage fallback patroon:**
```js
// Lees: nieuwe key eerst, legacy als fallback
const content = sessionStorage.getItem('aiPromptContent')
    ?? sessionStorage.getItem('claudePromptContent');
// Schrijf: altijd nieuwe key, verwijder legacy
sessionStorage.setItem('aiPromptContent', value);
sessionStorage.removeItem('claudePromptContent');
```

#### CSS

- Bestandsnaam `claude-chat.css` → `ai-chat.css` (rename)
- Interne CSS-klassen (`claude-*`) worden NIET hernoemd (te grote impact)
- HTML IDs (`#claudeStreamModal`, `#claudePromptCard`, etc.) worden NIET hernoemd (interne referenties)
- `site.css` referentie naar `.ql-insertClaudeCommand` wordt NIET hernoemd (Quill plugin naam)
- `editor-init.js` referentie naar `.claude-prompt-card-sticky` wordt NIET hernoemd (CSS class)
- Layout `main.php`: import bijwerken van `claude-chat.css` → `ai-chat.css`
- `mobile.css` commentaar referentie naar claude-chat.css bijwerken
- Layout `main.php`: controller ID checks bijwerken (`=== 'claude'` → `=== 'ai-chat'`) op 2 plaatsen (nav active state + pagina-specifieke logica)
- Layout `_bottom-nav.php`: controller ID check (`isActive('claude')` → `isActive('ai-chat')`) + hardcoded "Claude" label → dynamische providernaam

## Security overwegingen

### Provider-laag security

| Concern | Maatregel |
|---------|-----------|
| CLI command injection via prompt | Provider-implementatie MOET prompt escapen voor shell (bijv. `escapeshellarg()`). Dit is een implementatie-eis, niet geregeld via interface. |
| Provider identifier validation | `AiRun.provider` validatie via model rule: `/^[a-z][a-z0-9-]{1,48}$/` |
| Provider options sanitizatie | `ai_options` JSON wordt door provider geïnterpreteerd; provider MOET onbekende keys negeren en waarden valideren |
| Stream file path traversal | Stream file path constructie via `@app/storage/ai-runs/{id}.ndjson` waar `{id}` altijd een integer is (ActiveRecord PK) |
| Provider identifier in CLI commands | Provider identifier MAG NIET geïnterpoleerd worden in shell commands. CLI binary selectie MOET via een whitelist map (`['claude' => '/usr/local/bin/claude']`), niet via string concatenatie |
| Credential path validatie | Provider credentials (OAuth tokens, API keys) MOETEN via `realpath()` + prefix check gevalideerd worden. Pad MAG NIET configureerbaar zijn via user input |
| Stream file permissions | Stream files bevatten volledige prompts/responses. Directory MOET buiten webroot (huidige `@app/storage/` is veilig). Files MOETEN `0640` permissions hebben |
| Queue job deserialisatie | Na rename `RunClaudeJob` → `RunAiJob`: class alias toevoegen (`class_alias('app\jobs\RunAiJob', 'app\jobs\RunClaudeJob')`) in bootstrap. Voorkomt deserialisatie-fouten van bestaande queue jobs |

### RBAC migratie

| Scope | Oud | Nieuw |
|-------|-----|-------|
| Entity key (rbac.php) | `claude` | `ai-chat` (matcht controller ID van `AiChatController`) |
| Entity key (rbac.php) | `claudeRun` | `aiRun` |
| Permission (auth_item) | `viewClaudeRun` | `viewAiRun` |
| Permission (auth_item) | `updateClaudeRun` | `updateAiRun` |
| Rule class | `ClaudeRunOwnerRule` | `AiRunOwnerRule` |
| Controller mapping | `claude/*` | `ai-chat/*` |
| Roles sectie | `user` → `viewClaudeRun`, `updateClaudeRun` | `user` → `viewAiRun`, `updateAiRun` |
| Project entity | `claudeCommands` → `viewProject` | `aiCommands` → `viewProject` |
| Project entity | `claude` → `viewProject` | Verwijderen (deprecated action) |
| Note entity | `claude` → `viewNote` | `ai` → `viewNote` |

> **Let op:** RBAC permissions worden opgeslagen in `auth_item`, `auth_item_child` en `auth_rule` tabellen. Migratie moet deze tabellen ook updaten, niet alleen `rbac.php` config. De `roles` sectie in `rbac.php` die permission names aan rollen toekent moet ook worden bijgewerkt.

**RBAC migratie volgorde (in aparte migratie `m260216_000003_rename_claude_rbac_to_ai.php`):**
1. Nieuwe `AiRunOwnerRule` toevoegen aan `auth_rule` tabel
2. Permissions hernoemen via `$auth->update()` (`viewClaudeRun` → `viewAiRun`, etc.) — `auth_item_child` relaties volgen automatisch mee
3. Permissions updaten naar nieuwe rule name (`isAiRunOwner`)
4. Oude `ClaudeRunOwnerRule` verwijderen uit `auth_rule`
5. Idempotent: check `$auth->getPermission()` op bestaan vóór rename
6. `rbac.php` config handmatig bijwerken met alle entity keys, permission names en rule class referenties

### Endpoint security (compleet)

| Endpoint | RBAC | Extra validatie |
|----------|------|-----------------|
| `POST /ai/start-run` | projectOwner | Input: prompt (string, max length), options (JSON schema) |
| `GET /ai/stream-run` | aiRunOwner | SSE headers, stream token verificatie |
| `POST /ai/cancel-run` | aiRunOwner | Run moet status `running` hebben |
| `GET /ai/run-status` | aiRunOwner | - |
| `GET /ai/runs` | viewProject | Pagination, project scope |
| `POST /ai/delete-session` | aiRunOwner | Session moet bestaan |
| `GET /ai/usage` | viewProject | Provider moet `AiUsageProviderInterface` implementeren |
| `GET /ai/check-config` | viewProject | Provider moet `AiConfigProviderInterface` implementeren |
| `POST /ai/suggest-name` | viewProject | Input: content (string), max length validatie |
| `GET /ai/index` | viewProject | Project context required |
| `POST /ai/save` | aiRunOwner | Run moet status `completed` hebben |
| `POST /ai/cleanup` | viewProject | Confirmation required |
| `GET /ai/active-runs` | viewProject | Project scope |
| `POST /ai/summarize-session` | viewProject, `@` auth | Input: session_id (string) |
| `POST /ai/summarize-prompt` | viewProject, `@` auth | Input: content (string) |
| `POST /ai/summarize-response` | `@` auth | Input: content (string) |
| `POST /ai/stream` | viewProject | Legacy SSE wrapper, stream token verificatie |
| `POST /ai/cancel` | viewProject | Run moet actief zijn |
| `POST /ai/import-text` | viewProject | Input: content, note delegation |
| `POST /ai/import-markdown` | viewProject | Input: content, note delegation |
| `GET /project/ai-commands` | viewProject | Was: `/project/claude-commands` |

## Test scenarios

### Unit tests

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| AiRun model tableName | - | Returns `'{{%ai_run}}'` |
| AiRun model heeft provider kolom | AiRun met provider='claude' | Save succesvol, provider opgeslagen |
| AiRunStatus enum values | - | Zelfde waarden als voormalige ClaudeRunStatus |
| AiPermissionMode enum values | - | Zelfde waarden als voormalige ClaudePermissionMode |
| AiRunOwnerRule execute | User die eigenaar is | Returns true |
| AiRunOwnerRule execute | User die geen eigenaar is | Returns false |
| AiRunQuery scopes | forUser, forProject, active, terminal | Correcte query condities |
| ClaudeCliProvider implements AiProviderInterface | - | Instanceof check slaagt |
| ClaudeCliProvider implements alle optionele interfaces | - | Instanceof check slaagt voor Streaming, Workspace, Usage, Config |
| RunAiJob execute | AiRun met provider='claude' | Resolved AiProviderInterface uit DI, delegeert execute |
| AiRunCleanupService deleteSession | Session ID | Alle runs in session verwijderd |
| AiQuickHandler complete | Prompt met use case | Delegeert naar AiCompletionClient |
| Project model getAiOptions | Project met ai_options JSON | Returns decoded array |
| Project model getAiContext | Project met ai_context | Returns markdown string |
| DI container resolve | AiProviderInterface::class | Returns ClaudeCliProvider instance |
| AiChatController actionIndex | Authenticated user met project | Renders ai-chat/index view |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| Migratie forward + backward | safeUp dan safeDown | Tabel terug naar claude_run, kolommen terug |
| AiRun zonder provider | Insert zonder provider waarde | Default 'claude' wordt gebruikt |
| Provider identifier lengte | Provider string > 50 chars | Validatie error |
| Null ai_options | Project zonder ai_options | getAiOptions() returns lege array |
| Legacy sessionStorage | claudePromptContent bestaat, aiPromptContent niet | Fallback leest legacy key |
| Concurrent run limiet | MAX_CONCURRENT_RUNS overschreden | Validation error, run niet gestart |
| Stale run detectie | AiRun zonder heartbeat > threshold | Gedetecteerd door AiRunQuery::stale() |
| Workspace path na rename | Bestaande workspace directories | Pad generatie werkt correct met nieuwe service |
| Provider identifier met speciale chars | provider='../../etc' | Validatie error door regex rule |
| RBAC migratie bestaande permissions | auth_item tabel heeft 'claude' entry | Migratie update naar 'ai', relaties intact |
| Stream file pad met non-integer ID | Handmatig geconstrueerd pad | Integer-only pad constructie voorkomt traversal |
| Minimal provider (geen streaming) | Provider implementeert alleen AiProviderInterface | Controller valt terug op sync execute, geen errors |
| Minimal provider (geen usage) | Provider zonder AiUsageProviderInterface | Usage endpoint retourneert lege response, geen exception |
| Queue job class alias | Geserialiseerde `RunClaudeJob` in queue tabel | Deserialisatie slaagt via class alias, job wordt correct verwerkt als `RunAiJob` |
| RBAC migratie rollback | `safeDown()` van RBAC migratie | Permissions terug naar `viewClaudeRun`/`updateClaudeRun`, oude rule hersteld |
| `parseStreamResult` met Claude NDJSON | Claude stream-log met `type: result` en `type: system` events | Returns gestandaardiseerd `['text' => ..., 'session_id' => ..., 'metadata' => [...]]` |
| `parseStreamResult` met leeg stream-log | `null` of lege string | Returns default lege array zonder exception |
| ProjectController actionAiCommands | Authenticated user met project | Returns JSON met slash commands |
| Project afterSave workspace sync | Project met gewijzigde `ai_context` | Workspace sync via DI-resolved provider, niet via `Yii::$app->claudeWorkspaceService` |

### Test file migratie

Bestaande test files worden hernoemd (17 bestanden):

| Huidig | Nieuw |
|--------|-------|
| `tests/unit/models/ClaudeRunTest.php` | `tests/unit/models/AiRunTest.php` |
| `tests/unit/models/ClaudeRunSearchTest.php` | `tests/unit/models/AiRunSearchTest.php` |
| `tests/unit/models/ClaudeRunQueryTest.php` | `tests/unit/models/AiRunQueryTest.php` |
| `tests/unit/services/ClaudeCliServiceTest.php` | `tests/unit/services/ai/ClaudeCliProviderTest.php` |
| `tests/unit/services/ClaudeWorkspaceServiceTest.php` | (geïntegreerd in ClaudeCliProviderTest) |
| `tests/unit/services/ClaudeCliCompletionClientTest.php` | `tests/unit/services/ClaudeCliCompletionClientTest.php` (behouden, interne dep update) |
| `tests/unit/services/ClaudeStreamRelayServiceTest.php` | `tests/unit/services/AiStreamRelayServiceTest.php` |
| `tests/unit/services/ClaudeRunCleanupServiceTest.php` | `tests/unit/services/AiRunCleanupServiceTest.php` |
| `tests/unit/enums/ClaudeRunStatusTest.php` | `tests/unit/enums/AiRunStatusTest.php` |
| `tests/unit/rbac/ClaudeRunOwnerRuleTest.php` | `tests/unit/rbac/AiRunOwnerRuleTest.php` |
| `tests/unit/jobs/RunClaudeJobTest.php` | `tests/unit/jobs/RunAiJobTest.php` |
| `tests/unit/controllers/ClaudeControllerTest.php` | `tests/unit/controllers/AiChatControllerTest.php` |
| `tests/unit/commands/ClaudeRunControllerTest.php` | `tests/unit/commands/AiRunControllerTest.php` |
| `tests/unit/handlers/ClaudeQuickHandlerTest.php` | `tests/unit/handlers/AiQuickHandlerTest.php` |
| `tests/fixtures/ClaudeRunFixture.php` | `tests/fixtures/AiRunFixture.php` |
| `tests/fixtures/data/claude_runs.php` | `tests/fixtures/data/ai_runs.php` |
| `tests/unit/controllers/ProjectControllerTest.php` | (behouden, interne referenties updaten) |

### Nieuwe tests (interface compliance)

| Test | Doel |
|------|------|
| `AiProviderInterfaceTest` | Verifieert dat `ClaudeCliProvider` alle 4 optionele interfaces implementeert |
| `MinimalProviderTest` | Test met een mock-provider die ALLEEN `AiProviderInterface` implementeert — verifieert dat controller graceful degradeert |
| `AiRunProviderValidationTest` | Verifieert dat `AiRun.provider` regex validatie werkt voor valide/invalide identifiers |

### Bestaande fixture data

`tests/fixtures/data/claude_runs.php` → `ai_runs.php`: update `tableName` referentie naar `ai_run`, voeg `provider` kolom toe aan fixture data met waarde `'claude'`.

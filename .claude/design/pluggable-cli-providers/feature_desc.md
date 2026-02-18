# Feature: Pluggable CLI Providers — Per-Project Configuratie

## Samenvatting

Maak het toevoegen van nieuwe AI CLI providers (Codex, Gemini, etc.) zo eenvoudig als het implementeren van een provider-klasse en het registreren in de DI-config. Elke provider is op projectniveau configureerbaar via een eigen tab in de project-instellingen. Providers kunnen hun volledige kracht benutten via provider-specifieke opties die niet beperkt worden door de gedeelde interface.

## Probleemstelling

De multi-provider runtime selectie is geïmplementeerd: de `AiProviderRegistry` beheert providers, de chat-view biedt een provider-dropdown, en de job resolved de juiste provider. Maar:

1. **Projectconfiguratie is Claude-hardcoded** — de project form heeft één "Claude CLI Defaults" card met hardcoded model/permission mode opties. Een nieuwe provider heeft geen plek voor configuratie.
2. **Provider-specifieke opties gaan verloren** — de gedeelde interface kent alleen generieke `options[]`. Codex heeft bijv. `approval-mode` i.p.v. `permissionMode`, en andere models dan Claude. Die provider-specifieke opties moeten configureerbaar zijn zonder de interface te vervuilen.
3. **Snelle meegroei met CLI-updates** — wanneer een CLI een nieuw model of een nieuwe optie toevoegt, moet PromptManager dit snel kunnen ondersteunen zonder grote refactors.

## Kernprincipes

1. **Plugin-architectuur** — Een nieuwe CLI toevoegen = provider-klasse + DI-registratie + klaar. Geen wijzigingen in controller, job, of views nodig.
2. **Per-provider project-tabs** — Elke provider die `AiConfigProviderInterface` implementeert krijgt een eigen tab in de project-instellingen. Tab is er alleen als de provider beschikbaar is.
3. **Full provider power** — Provider-specifieke opties worden opgeslagen en doorgegeven als opaque `options[]` array. De provider interpreteert ze zelf, de controller geeft ze alleen door.
4. **Snel meegroeien** — Modellen, permission modes, en opties worden gedeclareerd door de provider zelf. Nieuwe modellen = provider-klasse updaten, geen view-wijzigingen.

## Huidige staat

### Wat al werkt (multi-provider-runtime-selection)
- `AiProviderRegistry` beheert providers als immutable singleton
- Chat view: provider dropdown, dynamische model/permission mode selects
- `AiRun.provider` kolom slaat geselecteerde provider op
- `RunAiJob` resolved provider uit run record
- Sync fallback voor non-streaming providers
- Cross-provider sessiedetectie

### Wat nog ontbreekt
- Per-provider project configuratie (tabs in project form)
- Provider-specifieke opties declaratie en opslag
- Workspace management per provider
- Een concrete tweede provider (bijv. Codex CLI)

---

## Functionele Requirements

### FR-0: Infrastructuur — Docker & Codex binary

Voordat een tweede CLI provider kan werken, moet de Docker-omgeving voorbereid zijn.

**Docker-compose wijzigingen:**
```yaml
# Credential mount voor Codex (naast bestaande Claude mounts)
- ${HOME}/.codex:/home/${USER_NAME}/.codex
```

Toe te voegen aan zowel `pma_yii` als `pma_queue` services — beide voeren CLI commands uit.

**Codex binary installatie:**
Codex CLI wordt geïnstalleerd via `npm install -g @openai/codex` in het Dockerfile. De binary moet beschikbaar zijn in `PATH` (standaard: `~/.local/bin` of `/usr/local/bin`).

**Authenticatie:**
Na installatie: `codex auth` in de container om OAuth login te voltooien. Credentials worden opgeslagen in `~/.codex/` (gemount volume — persistent).

**Acceptatiecriteria:**
- `docker-compose.yml`: `~/.codex/` mount op beide services
- Dockerfile: `npm install -g @openai/codex`
- `codex --version` draait succesvol in de container
- `codex auth` is uitgevoerd en credentials zijn persistent via mount

### FR-1: Provider declareert eigen configuratie-schema

Elke provider die `AiConfigProviderInterface` implementeert declareert welke opties hij ondersteunt. Dit schema drijft de UI (project form tabs + chat selects).

```php
interface AiConfigProviderInterface
{
    // Bestaand:
    public function getSupportedModels(): array;           // ['sonnet' => 'Sonnet', ...]
    public function getSupportedPermissionModes(): array;  // ['plan', 'dontAsk', ...]

    // Nieuw:
    /**
     * Declareert provider-specifieke configuratie-opties voor de project form.
     *
     * @return array<string, array{
     *   type: 'select'|'text'|'textarea'|'checkbox',
     *   label: string,
     *   hint?: string,
     *   placeholder?: string,
     *   options?: array<string, string>,
     *   default?: mixed,
     *   group?: string,
     * }>
     */
    public function getConfigSchema(): array;
}
```

**Voorbeeld Claude:**
```php
public function getConfigSchema(): array
{
    return [
        'allowedTools' => [
            'type' => 'text',
            'label' => 'Allowed Tools',
            'placeholder' => 'e.g. Read,Glob,Grep',
            'hint' => 'Comma-separated tool names',
        ],
        'disallowedTools' => [
            'type' => 'text',
            'label' => 'Disallowed Tools',
            'placeholder' => 'e.g. Bash,Write',
            'hint' => 'Comma-separated tool names',
        ],
        'appendSystemPrompt' => [
            'type' => 'textarea',
            'label' => 'Append to System Prompt',
            'placeholder' => 'Additional instructions appended to the system prompt',
        ],
    ];
}
```

**Voorbeeld Codex:**
```php
public function getConfigSchema(): array
{
    return [
        'approvalMode' => [
            'type' => 'select',
            'label' => 'Approval Mode',
            'options' => [
                '' => '(Use CLI default)',
                'suggest' => 'Suggest (show diff, ask approval)',
                'auto-edit' => 'Auto Edit (apply file edits, ask for commands)',
                'full-auto' => 'Full Auto (apply all, no approval)',
            ],
        ],
    ];
}
```

### FR-2: Per-provider project-opties opslag

Provider-specifieke opties worden opgeslagen in de bestaande `ai_options` JSON kolom van `Project`, genamespaced per provider identifier.

**Opslagstructuur in `Project.ai_options`:**
```json
{
    "claude": {
        "model": "sonnet",
        "permissionMode": "plan",
        "allowedTools": "Read,Glob",
        "disallowedTools": "Bash",
        "appendSystemPrompt": "Always respond in Dutch"
    },
    "codex": {
        "model": "codex-mini",
        "approvalMode": "auto-edit"
    },
    "_default": "claude"
}
```

**Acceptatiecriteria:**
- `Project::getAiOptionsForProvider(string $identifier): array` — retourneert opties voor één provider
- `Project::setAiOptionsForProvider(string $identifier, array $options): void` — slaat op genamespaced
- `Project::getDefaultProvider(): string` — retourneert `_default` key of registry default
- `Project::getAiOptions(): array` — backward-compatible: retourneert de opties van de default provider (zodat bestaande code werkt)
- Bestaande projecten: bij eerste save worden de huidige flat `ai_options` gemigreerd naar de genamespaced structuur onder de `claude` key

### FR-3: Per-provider tabs in project form

De project form toont dynamisch een tab per geregistreerde provider die `AiConfigProviderInterface` implementeert. Tabs worden server-side gerenderd.

**Layout:**
```
┌──────────┐┌──────────┐┌──────────┐
│ Claude   ││ Codex    ││ Context  │
└──────────┘└──────────┘└──────────┘
┌──────────────────────────────────┐
│ Model:          [Sonnet ▼]       │
│ Permission:     [Plan ▼]         │
│ Allowed Tools:  [____________]   │
│ Disallowed:     [____________]   │
│ System Prompt:  [____________]   │
│                 [____________]   │
│                                  │
│ Command Dropdown                 │
│ ┌──────────────────────────────┐ │
│ │ (blacklist/groups config)    │ │
│ └──────────────────────────────┘ │
└──────────────────────────────────┘
```

**Acceptatiecriteria:**
- Controller geeft `AiProviderRegistry::all()` door aan de project form view
- Per provider die `AiConfigProviderInterface` implementeert: één tab
- Tab-label = `$provider->getName()`
- Tab-inhoud:
  - **Standaard rij:** Model dropdown + Permission Mode dropdown (uit `getSupportedModels()` / `getSupportedPermissionModes()`)
  - **Provider-specifieke velden:** gerenderd vanuit `getConfigSchema()` (dynamische form builder)
  - **Command dropdown config:** alleen als provider `loadCommands()` ondersteunt (= `AiConfigProviderInterface`)
- Tab is alleen zichtbaar als de provider geregistreerd is in de registry
- Context tab (Quill editor voor `ai_context`) blijft apart als eigen tab — is provider-agnostisch
- Bij slechts één provider: geen tabs, directe weergave (huidige UX)
- Form data wordt opgeslagen als `ai_options[{provider_id}][{option_key}]`

### FR-4: Provider-specifieke opties doorstromen naar CLI

Wanneer een run wordt aangemaakt, worden de project-defaults van de geselecteerde provider samengevoegd met de runtime-selectie uit de chat view.

**Flow:**
1. Chat view stuurt: `{ provider: "codex", model: "codex-mini", approvalMode: "auto-edit" }`
2. Controller leest project-defaults: `$project->getAiOptionsForProvider('codex')`
3. Merge: runtime-selectie overschrijft project-defaults (bestaand patroon)
4. `$run->options` slaat de gemerged opties op als JSON
5. Job geeft `$run->getDecodedOptions()` door aan `$provider->execute()` of `$provider->executeStreaming()`
6. Provider interpreteert de opties — Codex vertaalt `approvalMode` naar `--approval-mode`, Claude vertaalt `permissionMode` naar `--permission-mode`

**Acceptatiecriteria:**
- `prepareRunRequest()` leest defaults van de geselecteerde provider (niet meer van de flat `ai_options`)
- Provider-specifieke keys worden niet gefilterd door een whitelist — de provider zelf bepaalt welke keys relevant zijn
- `buildCommand()` in elke provider vertaalt `$options` naar CLI flags
- Onbekende keys worden genegeerd (niet doorgestuurd als CLI flags)

### FR-5: Chat view dynamische provider-opties

De chat view toont provider-specifieke opties (model, permission mode, en custom opties) die dynamisch wisselen bij provider-selectie.

**Acceptatiecriteria:**
- `buildProviderData()` bevat naast `models` en `permissionModes` ook `configSchema` per provider
- Provider-specifieke velden worden dynamisch gerenderd in het settings panel
- Bij provider-wissel: velden wisselen mee
- Custom velden (uit `getConfigSchema()`) worden gerenderd als extra rij onder model/permission mode
- Custom velden waarden worden meegestuurd in het request body

### FR-6: Workspace management per provider

Elke provider die `AiWorkspaceProviderInterface` implementeert beheert zijn eigen workspace. De bestaande `syncConfig()` trigger in `Project::afterSave()` wordt provider-aware.

**Acceptatiecriteria:**
- `Project::afterSave()` roept `syncConfig()` aan op alle geregistreerde providers die `AiWorkspaceProviderInterface` implementeren
- Elke provider schrijft naar zijn eigen workspace directory: `@app/storage/projects/{project_id}/{provider_id}/`
- `ClaudeCliProvider` gebruikt `CLAUDE.md` en `.claude/settings.local.json`
- Codex zou `codex.md` of een eigen config-formaat kunnen gebruiken
- Workspace paths worden niet gedeeld tussen providers

### FR-7: Codex CLI als eerste plug-in provider

Als proof-of-concept wordt `CodexCliProvider` geïmplementeerd als tweede provider.

**Acceptatiecriteria:**
- `yii/services/ai/providers/CodexCliProvider.php`
- Implementeert: `AiProviderInterface`, optioneel `AiStreamingProviderInterface`, `AiConfigProviderInterface`
- `getIdentifier()`: `'codex'`
- `getName()`: `'Codex'`
- `getSupportedModels()`: models uit Codex CLI (`codex-mini`, etc.)
- `getSupportedPermissionModes()`: n.v.t. — Codex gebruikt `approval-mode` (eigen schema)
- `getConfigSchema()`: declareert `approvalMode` select
- `buildCommand()`: vertaalt opties naar `codex --approval-mode {mode} ...`
- `execute()` en optioneel `executeStreaming()`: roept Codex CLI aan
- Geregistreerd in `main.php` als `'aiProvider.codex'`

### FR-8: Frontend event abstractie voor real-time streaming

De huidige frontend JavaScript (`ai-chat/index.php`) parsed Claude-specifieke event types (`system`, `assistant`, `result`) om real-time streaming tekst, tool-use indicators, en sessie-info te tonen. Codex events (`thread.started`, `item.completed`, `turn.completed`) worden genegeerd.

**Probleem:** Met beslissing B-2 (raw events in stream-file) bereiken Codex events de browser, maar er is geen handler. Real-time weergave werkt niet voor Codex.

**Oplossing: provider-specifieke event handlers in JavaScript**

De frontend krijgt een event router die per provider de juiste handler aanroept:

```javascript
const eventHandlers = {
    claude: {
        onEvent(data) {
            if (data.type === 'system') onStreamSystem(data);
            else if (data.type === 'assistant') onStreamAssistant(data);
            else if (data.type === 'result') onStreamResult(data);
        }
    },
    codex: {
        onEvent(data) {
            if (data.type === 'thread.started') onCodexThreadStarted(data);
            else if (data.type === 'item.completed') onCodexItemCompleted(data);
            else if (data.type === 'turn.completed') onCodexTurnCompleted(data);
            else if (data.type === 'error') onCodexError(data);
        }
    }
};
```

**Acceptatiecriteria:**
- `onStreamEvent()` dispatch naar provider-specifieke handler op basis van `activeProvider`
- Claude handlers: ongewijzigd (bestaande `onStreamSystem`, `onStreamAssistant`, `onStreamResult`)
- Codex handlers: `onCodexItemCompleted()` toont streaming tekst en tool-use, `onCodexTurnCompleted()` toont usage
- Tool-use display is provider-aware: Claude tools (Read/Edit/Bash) vs Codex tools (shell/apply_patch)
- Fallback: onbekende event types worden stilletjes genegeerd
- Config check badge (`hasCLAUDE_MD`) wordt provider-aware: toont `CLAUDE.md` of `codex.md` afhankelijk van actieve provider

---

## Migratiestrategie bestaande projecten

Bestaande projecten hebben `ai_options` als flat JSON (niet genamespaced):
```json
{"model": "sonnet", "permissionMode": "plan", "allowedTools": "Read,Glob"}
```

Bij het lezen wordt dit automatisch gemigreerd:
```php
public function getAiOptionsForProvider(string $identifier): array
{
    $options = json_decode($this->ai_options, true) ?? [];

    // Nieuwe structuur: genamespaced per provider
    if (isset($options[$identifier]) && is_array($options[$identifier]))
        return $options[$identifier];

    // Legacy structuur: flat options → behandel als default provider opties
    if ($identifier === $this->getDefaultProvider() && !$this->isNamespacedOptions($options))
        return $options;

    return [];
}
```

Bij de eerste save via het nieuwe formulier worden opties automatisch genamespaced. Geen database migratie nodig.

---

## Richtlijnen voor nieuwe provider-implementaties

### Minimale provider (alleen sync execute)

```php
class MyCliProvider implements AiProviderInterface
{
    public function getIdentifier(): string { return 'my-cli'; }
    public function getName(): string { return 'My CLI'; }
    public function execute(...): array { /* shell aanroep */ }
    public function cancelProcess(string $streamToken): bool { /* kill */ }
}
```

Resultaat: provider werkt in chat, sync fallback in job, geen project-configuratie tab, geen model/permission keuze.

### Volledige provider (streaming + configuratie + workspace)

```php
class MyCliProvider implements
    AiProviderInterface,
    AiStreamingProviderInterface,
    AiConfigProviderInterface,
    AiWorkspaceProviderInterface,
    AiUsageProviderInterface
{
    // AiProviderInterface
    public function execute(...): array { }
    public function cancelProcess(string $streamToken): bool { }
    public function getIdentifier(): string { }
    public function getName(): string { }

    // AiStreamingProviderInterface
    public function executeStreaming(...): array { }
    public function parseStreamResult(?string $streamLog): array { }

    // AiConfigProviderInterface
    public function getSupportedModels(): array { }
    public function getSupportedPermissionModes(): array { }
    public function getConfigSchema(): array { }              // Nieuw
    public function hasConfig(string $path): array { }
    public function checkConfig(string $path): array { }
    public function loadCommands(string $directory): array { }

    // AiWorkspaceProviderInterface
    public function ensureWorkspace(Project $project): string { }
    public function syncConfig(Project $project): void { }
    public function deleteWorkspace(Project $project): void { }
    public function getWorkspacePath(Project $project): string { }
    public function getDefaultWorkspacePath(): string { }

    // AiUsageProviderInterface
    public function getUsage(): array { }
}
```

Resultaat: volledige integratie — streaming, project-tab met custom opties, workspace sync, usage tracking.

### Registratie

```php
// yii/config/main.php
'singletons' => [
    'aiProvider.claude' => ClaudeCliProvider::class,
    'aiProvider.codex' => CodexCliProvider::class,         // Nieuw
    AiProviderRegistry::class => function () {
        return new AiProviderRegistry([
            Yii::$container->get('aiProvider.claude'),
            Yii::$container->get('aiProvider.codex'),      // Nieuw
        ]);
    },
],
```

---

## Snel meegroeien met CLI-updates

Het systeem is ontworpen om snel mee te groeien:

| CLI wijziging | Wat te doen in PromptManager |
|---------------|------------------------------|
| Nieuw model toegevoegd | `getSupportedModels()` updaten in provider-klasse |
| Nieuwe CLI flag | `getConfigSchema()` uitbreiden + `buildCommand()` flag toevoegen |
| Nieuwe permission/approval mode | `getSupportedPermissionModes()` of `getConfigSchema()` updaten |
| Streaming format veranderd | `executeStreaming()` en/of `parseStreamResult()` aanpassen |
| Geheel nieuwe CLI | Nieuwe provider-klasse + DI-registratie |

Geen van deze wijzigingen raakt de controller, job, views, of andere providers.

---

## Implementatievolgorde

0. **FR-0**: Docker infra — `~/.codex/` mount + `npm install -g @openai/codex` in Dockerfile
1. **FR-1**: `getConfigSchema()` op `AiConfigProviderInterface` + implementatie in `ClaudeCliProvider`
2. **FR-2**: Genamespaced opties opslag in `Project` model
3. **FR-3**: Per-provider tabs in project form (vervangt hardcoded Claude card)
4. **FR-4**: Provider-specifieke opties doorstromen (verwijder `allowedKeys` whitelist, prepareRunRequest + buildCommand)
5. **FR-5**: Chat view dynamische custom velden
6. **B-1**: `RunAiJob` refactor — verplaats `extractResultText()`/`extractMetadata()` naar `ClaudeCliProvider::parseStreamResult()`
7. **FR-8**: Frontend event abstractie — provider-specifieke event handlers in JavaScript
8. **FR-6**: Workspace management per provider (inclusief workspace path migratie naar `{id}/{provider_id}/`)
9. **FR-7**: `CodexCliProvider` als proof-of-concept

**Stap 6 (B-1) is een prerequisite voor stap 9 (FR-7)** — zonder gedelegeerde parsing kan Codex niet werken.
**Stap 7 (FR-8) is een prerequisite voor stap 9 (FR-7)** — zonder frontend handlers toont Codex geen streaming output.

---

## Beantwoorde vragen

### 1. Codex CLI binary beschikbaarheid
Codex CLI moet geïnstalleerd worden in de Docker container (`npm install -g @openai/codex`). Authenticatie gaat via OAuth (`codex auth`) met Pro-abonnement — geen API key nodig. Credential-bestanden (`~/.codex/`) moeten gemount worden in Docker, vergelijkbaar met Claude's `~/.claude/`.

### 2. Codex output format
Codex CLI ondersteunt JSONL streaming via `codex exec --json`. JSONL = NDJSON — zelfde formaat, andere event types:

| Aspect | Claude CLI | Codex CLI |
|--------|-----------|-----------|
| Flag | `--output-format stream-json` | `--json` |
| Events | `assistant`, `result`, `system` | `thread.started`, `item.completed`, `turn.completed`, `error` |
| Resultaat | `{"type":"result","result":"..."}` | `{"type":"item.completed","item":{"type":"agent_message","text":"..."}}` |
| Token usage | In `result` event | In `turn.completed` event met `usage` object |
| Progressie | Alles via stdout | Stderr (progress) + stdout (JSONL events) |

`CodexCliProvider::parseStreamResult()` vertaalt Codex events naar het interne formaat. De NDJSON stream-file + SSE relay werkt ongewijzigd.

### 3. Codex session management
Codex ondersteunt session resume via `codex exec resume --last` of `codex exec resume <SESSION_ID>`. Sessie-IDs zijn UUIDs (`thread_id` uit het `thread.started` event). Claude gebruikt `--resume <id>` als flag, Codex gebruikt `resume <id>` als subcommand — `buildCommand()` vertaalt dit.

## Architectuurbeslissingen

### B-1: Stream event parsing — Option B (provider delegeert)

**Beslissing:** `RunAiJob` delegeert resultaat- en metadata-extractie aan de provider via `parseStreamResult()`, in plaats van zelf elk provider-specifiek event format te kennen.

**Rationale:** Elke CLI produceert eigen event types en structuren. Als `RunAiJob` deze kent, groeit er tight coupling. Door extractie te delegeren aan de provider blijft de job generiek en hoeft bij een nieuw event format alleen de provider aangepast te worden.

#### Huidige situatie: dubbele logica

`RunAiJob` heeft drie private methoden die Claude-specifiek NDJSON parsen:

| RunAiJob methode | Zoekt naar | Claude event |
|---|---|---|
| `extractResultText()` | `type === 'result'` → `$decoded['result']` | `result` |
| `extractMetadata()` | `type === 'result'` → `duration_ms`, `session_id`, `num_turns`, `modelUsage` | `result` |
| `extractSessionId()` | `type === 'system'` → `$decoded['session_id']`, fallback `type === 'result'` | `system` / `result` |

`ClaudeCliProvider::parseStreamResult()` doet **exact dezelfde extractie** — al geïmplementeerd, zelfde logica, zelfde event types. De drie RunAiJob methoden zijn dus redundant.

#### Gewenste situatie: één parseStreamResult() call

```
RunAiJob                          Provider
   │                                 │
   │  $provider->parseStreamResult($streamLog)
   │────────────────────────────────>│
   │                                 │  (provider-specifieke NDJSON parsing)
   │  ['text' => ...,                │
   │   'session_id' => ...,          │
   │   'metadata' => [...]]          │
   │<────────────────────────────────│
   │                                 │
   │  $run->markCompleted(           │
   │      $parsed['text'],           │
   │      $parsed['metadata'],       │
   │      $streamLog                 │
   │  );                             │
```

#### Bestaand interface-contract (ongewijzigd)

```php
// AiStreamingProviderInterface::parseStreamResult()
// @return array{text: string, session_id: ?string, metadata: array}
```

Dit contract is al gedefinieerd en `ClaudeCliProvider` implementeert het al correct:

```php
// ClaudeCliProvider::parseStreamResult() — bestaande implementatie
return ['text' => $text, 'session_id' => $sessionId, 'metadata' => $metadata];
// metadata = ['duration_ms' => ..., 'num_turns' => ..., 'modelUsage' => ...]
```

**Geen interface-wijziging nodig.** Het return format is al correct.

#### Concrete refactorstappen

**Stap 1: RunAiJob — streaming path refactoren**

Huidige code (regels 148-158):
```php
$this->extractSessionId($run, $streamLog);
// ...
$resultText = $this->extractResultText($streamLog);
$metadata = $this->extractMetadata($streamLog, $result);
$run->markCompleted($resultText, $metadata, $streamLog);
```

Wordt:
```php
$parsed = $provider->parseStreamResult($streamLog);
if ($parsed['session_id'] !== null) {
    $run->setSessionIdFromResult($parsed['session_id']);
}
$run->markCompleted($parsed['text'], $parsed['metadata'], $streamLog);
```

**Stap 2: RunAiJob — sync fallback refactoren**

Huidige code (regels 114-141) schrijft een Claude-shaped `{"type":"result","result":"..."}` event naar de stream-file. Dit werkt alleen omdat `extractResultText()` datzelfde Claude-format terugverwacht.

Na refactor: de sync fallback schrijft het sync-resultaat **niet meer als NDJSON event**. In plaats daarvan converteert de job het `execute()` resultaat direct naar het `parseStreamResult()` return format:

```php
// Sync fallback — geen NDJSON event nodig, direct parsen uit execute() resultaat
$syncResult = $provider->execute(...);

// Schrijf raw output naar stream-file (voor relay)
fwrite($streamFile, json_encode(['type' => 'sync_result', 'text' => $syncResult['output'] ?? '']) . "\n");
fflush($streamFile);

$result = ['exitCode' => $syncResult['exitCode'], 'error' => $syncResult['error'] ?? ''];

// Bouw parsed resultaat direct uit sync response (geen parseStreamResult nodig)
$parsed = [
    'text' => $syncResult['output'] ?? '',
    'session_id' => $syncResult['session_id'] ?? null,
    'metadata' => array_filter([
        'duration_ms' => $syncResult['duration_ms'] ?? null,
        'num_turns' => $syncResult['num_turns'] ?? null,
        'modelUsage' => $syncResult['modelUsage'] ?? null,
    ]),
];
```

**Stap 3: RunAiJob — extractSessionId() mid-stream**

Het huidige `extractSessionId()` wordt aangeroepen direct na streaming eindigt (regel 148), vóór `markCompleted()`. Dit is om de session_id zo vroeg mogelijk in de DB te hebben.

Na refactor wordt dit meegenomen in de `parseStreamResult()` call — het session_id zit al in het return format. De mid-stream timing verschuift niet significant omdat `extractSessionId()` nu al pas na streaming wordt aangeroepen, niet tussendoor.

**Stap 4: Verwijder private methoden**

Na stappen 1-3 zijn deze methoden ongebruikt en worden verwijderd:
- `RunAiJob::extractResultText()`
- `RunAiJob::extractMetadata()`
- `RunAiJob::extractSessionId()`

#### Non-streaming providers en parseStreamResult()

Een provider die alleen `AiProviderInterface` implementeert (niet `AiStreamingProviderInterface`) heeft geen `parseStreamResult()`. Voor de sync fallback path is dat geen probleem — het `parsed` resultaat wordt direct uit het `execute()` return value opgebouwd (zie stap 2). De job hoeft `parseStreamResult()` alleen aan te roepen op de streaming path.

#### Acceptatiecriteria

- `RunAiJob` roept geen Claude-specifieke event types meer aan (`'result'`, `'system'`)
- Streaming path: `$provider->parseStreamResult($streamLog)` → `markCompleted()`
- Sync path: direct mapping van `execute()` resultaat → `markCompleted()`
- Drie private extract-methoden verwijderd uit `RunAiJob`
- `ClaudeCliProvider::parseStreamResult()` ongewijzigd (bevat al de juiste logica)
- Bestaande tests in `RunAiJobTest` aangepast voor nieuwe flow
- Alle 65 bestaande tests blijven groen

### B-2: NDJSON stream-file format — raw events

**Beslissing:** De stream-file bevat de ruwe NDJSON events van de CLI, niet vertaald naar een gestandaardiseerd formaat.

**Rationale:** De SSE relay leest de stream-file en stuurt events naar de browser. Door ruwe events door te sturen kan de frontend in de toekomst provider-specifieke info tonen (bijv. Codex sandbox status) zonder backend-wijzigingen.

**Uitzondering: sync fallback.** Bij non-streaming providers schrijft de job één `{"type":"sync_result","text":"..."}` event. Dit is een intern event type (niet provider-specifiek) dat de SSE relay kan doorsturen. De frontend herkent dit als generiek sync-resultaat.

---

## Concrete verschillen Claude CLI vs Codex CLI

### Command structuur

| Aspect | Claude CLI | Codex CLI |
|--------|-----------|-----------|
| Interactief | `claude` | `codex` |
| Non-interactief | `claude --print` | `codex exec` |
| Met streaming | `claude --output-format stream-json` | `codex exec --json` |
| Resume sessie | `claude --resume <id>` (flag) | `codex exec resume <id>` (subcommand) |
| System prompt | `--append-system-prompt "..."` | `--instructions "..."` |
| Max turns | `--max-turns N` | Niet beschikbaar |
| Working dir | Vanuit workspace dir | Vanuit workspace dir |

### Permission / Sandbox model

| Aspect | Claude CLI | Codex CLI |
|--------|-----------|-----------|
| Primair | `--permission-mode` (1 as) | `--approval-mode` + `--sandbox` (2 assen) |
| Modes | `plan`, `autoEdit`, `dontAsk` | `suggest`, `auto-edit`, `full-auto` |
| Sandbox | Niet van toepassing | `--sandbox=docker` / `--sandbox=off` |
| PromptManager mapping | `permissionMode` option key | `approvalMode` + optioneel `sandbox` option keys |

**Impact:** `getSupportedPermissionModes()` retourneert lege array voor Codex. In plaats daarvan declareert Codex `approvalMode` en optioneel `sandbox` via `getConfigSchema()`.

### Streaming event types

| Claude event | Codex equivalent | Inhoud |
|-------------|-----------------|--------|
| `assistant` (text) | `item.completed` (agent_message) | Tussentijdse tekst output |
| `assistant` (tool_use) | `item.completed` (function_call) | Tool/functie aanroep |
| `result` | Laatste `item.completed` | Eindresultaat tekst |
| `system` (init) | `thread.started` | Sessie start + thread_id |
| — | `turn.completed` | Turn afgerond + usage stats |
| — | `error` | Foutmelding |

**Impact op `parseStreamResult()`:**
- Claude: zoekt `type: "result"` event voor output, `type: "system"` voor sessie-ID
- Codex: zoekt laatste `item.completed` met `agent_message` type voor output, `thread.started` voor sessie-ID, `turn.completed` voor usage

### Authenticatie

| Aspect | Claude CLI | Codex CLI |
|--------|-----------|-----------|
| Methode | OAuth / Pro abonnement | OAuth / Pro abonnement |
| Setup | `claude auth` | `codex auth` |
| Credentials | `~/.claude/` | `~/.codex/` |
| API key | Niet nodig | Niet nodig |
| Docker mount | `~/.claude/` → container | `~/.codex/` → container |

### Workspace management

| Aspect | Claude CLI | Codex CLI |
|--------|-----------|-----------|
| Project config | `CLAUDE.md` | `codex.md` of `AGENTS.md` |
| Settings | `.claude/settings.local.json` | Niet van toepassing |
| Workspace dir | `storage/projects/{id}/claude/` | `storage/projects/{id}/codex/` |
| Config sync | `CLAUDE.md` + `.claude/settings.local.json` | `codex.md` (instructies) |

---

## Bekende technische schuld (bewust geparkeerd)

Items die niet binnen scope van deze feature vallen, maar wel geraakt worden. Documentatie als bewuste keuze om later op te pakken.

| # | Locatie | Probleem | Impact | Wanneer oppakken |
|---|---------|----------|--------|-----------------|
| 1 | `ProjectController::actionDelete()` | Roept alleen Claude's `deleteWorkspace()` aan. Bij meerdere providers moeten alle workspace directories opgeruimd worden. | Orphaned directories bij project verwijdering | FR-6 (workspace management) |
| 2 | `AiRun` model | Geen `stream_token` kolom — `actionCancelRun()` roept `cancelProcess('')` aan, wat een no-op is. Bestaande bug, raakt alle providers. | Cancel werkt niet via async run path | Los op als aparte bugfix vóór FR-7 |
| 3 | `actionSummarizeSession()` | Hardcoded check op `'sonnet'` model en `'plan'` permission mode als defaults. Bij Codex als default provider falen beide checks stilletjes. | Sessie-samenvatting draait zonder model/mode voorkeur | FR-4 (opties doorstromen) |
| 4 | `AiQuickHandler` / `AiCompletionClient` | Sessie-samenvattingen, prompt titels, en response summarization gebruiken altijd Claude API — ongeacht welke provider de run deed. | Functioneel acceptabel (samenvatting ≠ chat provider) | Bewust: geen actie nodig |
| 5 | Frontend DOM IDs | Alle element-IDs heten `claude-*` (`claude-combined-bar`, `claude-send-btn`, etc.). Functioneel geen probleem maar verwarrend bij multi-provider. | Geen functionele impact | Optioneel: hernoem naar `ai-*` bij grotere frontend refactor |
| 6 | `actionCheckConfig()` + frontend | Response keys `hasCLAUDE_MD`/`hasClaudeDir` — badge toont "CLAUDE.md" zelfs bij Codex. | Misleidende config badge | FR-8 (frontend event abstractie) |
| 7 | ~~`RunAiJob` sync fallback~~ | ~~Syntheseert Claude-shaped event.~~ **Opgelost in B-1 stap 2** — sync fallback schrijft nu provider-agnostisch `sync_result` event en bouwt parsed resultaat direct uit `execute()` return value. | — | — |

## Open vragen

Geen.

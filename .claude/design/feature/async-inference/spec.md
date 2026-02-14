# Feature: Async Claude Inference

## Samenvatting

Claude CLI inference draait volledig los van de browser-sessie. Inference wordt gestart als background job, overleeft browser-sluiting, en is per gebruiker opvraagbaar en hervaarbaar via SSE stream relay.

## User story

Als PromptManager-gebruiker wil ik dat een Claude inference doorloopt op de server als ik mijn browser sluit, wegnavigeer, of mijn device wissel, zodat ik geen werk verlies en later het resultaat kan bekijken of aanhaken op een lopende run.

---

## Functionele requirements

### FR-1: Fire-and-forget inference starten

- Beschrijving: Gebruiker stuurt een prompt via de bestaande Claude chat UI. Het systeem maakt een `ClaudeRun` record aan en plaatst een job in de queue. De browser ontvangt een `runId` terug.
- Acceptatiecriteria:
  - [ ] POST `/claude/start-run?p={projectId}` maakt een `ClaudeRun` record met status `pending`
  - [ ] Response bevat `{ success: true, runId: int, promptMarkdown: string }`
  - [ ] Een `RunClaudeJob` wordt naar de queue gepusht
  - [ ] De run bevat `user_id`, `project_id`, `session_id` (indien resume), `prompt_markdown`, `options`, `working_directory`

### FR-2: Live stream relay (SSE)

- Beschrijving: Browser kan aanhaken op een lopende run via SSE. De worker schrijft stream events naar een file op disk; de SSE endpoint relay't deze naar de browser.
- Acceptatiecriteria:
  - [ ] GET `/claude/stream-run?runId={id}&offset={bytes}` opent een SSE verbinding
  - [ ] Stream-file (`storage/claude-runs/{run_id}.ndjson`) wordt regel-voor-regel doorgestuurd
  - [ ] Bij offset > 0 worden alleen events vanaf dat punt verstuurd (reconnect)
  - [ ] Bij `[DONE]` wordt de verbinding gesloten
  - [ ] Als de run nog niet gestart is (geen stream-file), stuurt het endpoint `{"type":"waiting"}` events (max 10s)

### FR-3: Reconnect bij pagina-herlaad

- Beschrijving: Bij het openen van de Claude-pagina checkt de frontend of er actieve runs zijn voor dit project en haakt automatisch aan.
- Acceptatiecriteria:
  - [ ] Bij pagina-load wordt `GET /claude/active-runs?p={projectId}` aangeroepen
  - [ ] Als er een `running` run is, wordt automatisch de stream geopend (offset=0 voor volledige replay)
  - [ ] Als er een recent `completed` run is (niet ouder dan huidige sessie), wordt het resultaat getoond
  - [ ] Gemiste events worden volledig gereplayd via offset-mechanisme

### FR-4: Run annuleren

- Beschrijving: Gebruiker kan een lopende run annuleren. De worker detecteert de annulering via DB status-poll en stopt het CLI process.
- Acceptatiecriteria:
  - [ ] POST `/claude/cancel-run?runId={id}` zet de run status naar `cancelled`
  - [ ] De worker pollt de status elke 10 seconden en stopt bij `cancelled`
  - [ ] Partial output wordt bewaard in `stream_log`
  - [ ] Cancel werkt ook na browser-sluiting (via runs-overzicht)

### FR-5: Concurrency limiet

- Beschrijving: Maximaal 3 parallelle runs per gebruiker om misbruik te voorkomen.
- Acceptatiecriteria:
  - [ ] Bij `start-run`: tel actieve runs (status `pending` of `running`) voor deze user
  - [ ] Bij >= 3 actieve runs: HTTP 429 met melding "Maximum concurrent runs reached (3)."
  - [ ] Voltooide/gefaalde/geannuleerde runs tellen niet mee

### FR-6: Stale run detectie

- Beschrijving: Runs waarvan de worker crasht worden automatisch als `failed` gemarkeerd via heartbeat-mechanisme.
- Acceptatiecriteria:
  - [ ] Worker schrijft `updated_at` elke 30 seconden als heartbeat
  - [ ] Console command `claude-run/cleanup-stale` markeert runs als `failed` wanneer `updated_at` > 5 minuten oud EN status = `running`
  - [ ] Command draait via cron elke 5 minuten
  - [ ] Gebruiker ziet foutmelding, niet eindeloos "running"

### FR-7: Session continuity

- Beschrijving: Follow-up prompts sluiten aan bij een bestaande Claude sessie via `--resume`.
- Acceptatiecriteria:
  - [ ] Frontend stuurt `sessionId` mee bij `start-run` (indien beschikbaar)
  - [ ] De job geeft `--resume {sessionId}` door aan de CLI
  - [ ] Nieuwe run krijgt eigen `id` maar deelt `session_id` met eerdere runs in dezelfde conversatie

### FR-8: Migratie van directe streaming

- Beschrijving: De bestaande `actionStream` wordt eerst een wrapper die de async flow aanroept, daarna volledig verwijderd.
- Acceptatiecriteria:
  - [ ] Fase 1: `actionStream` roept intern `createRun()` + `actionStreamRun()` aan
  - [ ] Fase 2 (na validatie): `actionStream`, `actionRun`, `actionCancel` verwijderd
  - [ ] Alle bestaande features (save to note, summarize, session continue) werken via async pad

---

## Gebruikersflow

### Happy path (browser open)
1. Gebruiker opent Claude-pagina voor project X
2. Typt prompt in Quill editor, klikt Send
3. Frontend stuurt POST `/claude/start-run?p=X` → ontvangt `{ runId: 123 }`
4. Frontend opent GET `/claude/stream-run?runId=123&offset=0` → SSE verbinding
5. Worker pikt job op, start CLI, schrijft stream events naar file
6. SSE relay stuurt events door → zelfde streaming ervaring als voorheen
7. Run voltooid → status `completed`, resultaat opgeslagen
8. Frontend toont resultaat met metadata (tokens, model, duur)

### Browser-sluiting scenario
1. Gebruiker stuurt prompt, ziet "thinking..." animatie
2. Browser sluit (navigatie, crash, tab dicht)
3. Worker loopt door — geen `connection_aborted()` kill (CLI context)
4. Run wordt `completed` (of `failed`)
5. Gebruiker opent pagina opnieuw
6. Frontend checkt `active-runs` → vindt running/completed run
7. Bij running: haakt aan op stream (offset=0, volledige replay)
8. Bij completed: toont resultaat direct

### Cancel na browser-sluiting
1. Gebruiker opent pagina, ziet lopende run
2. Klikt Cancel
3. Frontend POST `/claude/cancel-run?runId=X`
4. DB status → `cancelled`
5. Worker pollt status (elke 10s), ziet `cancelled`, stopt CLI process
6. Partial output bewaard

---

## Edge cases

| Case | Gedrag |
|------|--------|
| Worker crash tijdens inference | Heartbeat stopt → stale detector markeert als `failed` na 5 min |
| Meerdere runs tegelijk | Toestaan tot max 3 per user. Badge toont aantal actieve runs |
| Zeer grote stream_log (>10MB) | LONGTEXT in DB (max 4GB). Bulk write na completion. Stream-files cleanup na 24h |
| PID hergebruik na restart | PID-namespace per container isoleert. Cancel gaat via DB, niet via PID |
| Stream-file permissies cross-container | Gedeeld Docker volume, zelfde user (USER_ID build arg), umask(0002) |
| Race: run start + stream connect | SSE endpoint wacht max 10s tot stream-file verschijnt, stuurt `waiting` events |
| Concurrent SSE lezers (2 tabs) | Beide lezen zelfde file (read-only). Geen conflict |
| Queue worker verouderde code na deploy | `docker restart pma_queue` na deploy (deploy hook) |
| `$onLine` niet aangeroepen bij stilte | Claude CLI streaming schrijft per-token via `--output-format stream-json`. Niet realistisch in productie |
| Docker Desktop macOS file I/O | VirtioFS (default) + Apple SSD: geen merkbare impact |
| Run gestart maar worker nog niet opgepikt (3s poll delay) | Frontend toont "Starting inference..." animatie direct bij klik |

---

## Domeinmodel

### Nieuwe entiteit: `ClaudeRun`

| Attribuut | Type | Beschrijving |
|-----------|------|-------------|
| `id` | int (unsigned, auto-increment) | Primary key |
| `user_id` | int (FK → user) | Eigenaar |
| `project_id` | int (FK → project) | Project context |
| `session_id` | varchar(191), nullable | Claude CLI session ID (gezet bij eerste `system.init` event) |
| `status` | enum(`pending`,`running`,`completed`,`failed`,`cancelled`) | Default: `pending` |
| `prompt_markdown` | longtext | De prompt zoals verstuurd naar Claude CLI |
| `prompt_summary` | varchar(255), nullable | Eerste 255 chars van prompt (voor lijstweergave) |
| `options` | json, nullable | CLI opties (model, permissionMode, etc.) |
| `working_directory` | varchar(500), nullable | Effectieve working directory |
| `stream_log` | longtext, nullable | NDJSON van alle stream events (bulk write bij completion) |
| `result_text` | longtext, nullable | Eindresultaat (markdown tekst) |
| `result_metadata` | json, nullable | `{duration_ms, session_id, num_turns, modelUsage}` |
| `error_message` | text, nullable | Foutmelding bij `failed` status |
| `pid` | int unsigned, nullable | Worker process ID (null als process klaar is) |
| `started_at` | datetime, nullable | Moment dat worker het oppikt |
| `completed_at` | datetime, nullable | Moment dat inference eindigt |
| `created_at` | datetime | Aanmaakmoment (via TimestampTrait) |
| `updated_at` | datetime | Laatste wijziging / heartbeat |

### Statusovergangen

```
[*] → pending → running → completed
                       → failed
                       → cancelled
```

- `pending → running`: worker pikt job op (atomaire UPDATE WHERE status='pending')
- `running → completed`: CLI exit code 0, resultaat geparsed
- `running → failed`: CLI exit ≠ 0 / timeout / worker crash (stale detector)
- `running → cancelled`: user cancel via DB status-poll

### Relaties

- `ClaudeRun` belongsTo `User` (via `user_id`)
- `ClaudeRun` belongsTo `Project` (via `project_id`)
- Conceptueel gekoppeld aan Claude CLI session (via `session_id`), maar geen FK

### Nieuwe enum: `ClaudeRunStatus`

| Waarde | Label |
|--------|-------|
| `pending` | Pending |
| `running` | Running |
| `completed` | Completed |
| `failed` | Failed |
| `cancelled` | Cancelled |

Helper methods: `values()`, `labels()`, `activeValues()` (pending+running), `terminalValues()` (completed+failed+cancelled).

### Indices

| Index | Kolommen | Rationale |
|-------|----------|-----------|
| `idx_claude_run_user_status` | `(user_id, status)` | Primaire query: "mijn lopende/recente runs" |
| `idx_claude_run_project` | `(project_id)` | Filter op project-pagina |
| `idx_claude_run_session` | `(session_id)` | Opzoeken runs in dezelfde conversatie |

---

## Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| `ClaudeRunStatus` | Enum | `yii/common/enums/ClaudeRunStatus.php` | Nieuw |
| `ClaudeRun` | Model | `yii/models/ClaudeRun.php` | Nieuw |
| `ClaudeRunQuery` | Query | `yii/models/query/ClaudeRunQuery.php` | Nieuw |
| `RunClaudeJob` | Job | `yii/jobs/RunClaudeJob.php` | Nieuw |
| `ClaudeStreamRelayService` | Service | `yii/services/ClaudeStreamRelayService.php` | Nieuw |
| `ClaudeRunOwnerRule` | RBAC | `yii/rbac/ClaudeRunOwnerRule.php` | Nieuw |
| `ClaudeRunController` (console) | Command | `yii/commands/ClaudeRunController.php` | Nieuw |
| Migration: `claude_run` tabel | Migration | `yii/migrations/m260214_000001_create_claude_run_table.php` | Nieuw |
| `ClaudeCliService` | Service | `yii/services/ClaudeCliService.php` | Wijzigen: CLI guard in `connection_aborted()`, conditionele PID store/clear, null streamToken handling |
| `ClaudeController` | Controller | `yii/controllers/ClaudeController.php` | Wijzigen: 5 nieuwe actions (`startRun`, `streamRun`, `cancelRun`, `runStatus`, `activeRuns`) + `createRun()` helper + migratie wrapper voor `actionStream` |
| Queue config | Config | `yii/config/main.php` | Wijzigen: `queue` component toevoegen |
| RBAC config | Config | `yii/config/rbac.php` | Wijzigen: `claudeRun` entity permissions toevoegen |
| Claude view | View/JS | `yii/views/claude/index.php` | Wijzigen: twee-staps send flow, reconnect, cancel via runId, active runs badge |
| Docker worker | Infra | `docker-compose.yml` | Wijzigen: `pma_queue` service toevoegen |
| Composer | Dependency | `composer.json` | Wijzigen: `yiisoft/yii2-queue` toevoegen |

---

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| `ClaudeCliService::executeStreaming()` | `yii/services/ClaudeCliService.php:205-298` | Aangeroepen door `RunClaudeJob`, met 4 kleine aanpassingen |
| `ClaudeCliService::buildCommand()` | `yii/services/ClaudeCliService.php:403-468` | Ongewijzigd hergebruikt via executeStreaming |
| `ClaudeController::prepareClaudeRequest()` | `yii/controllers/ClaudeController.php:450-498` | Hergebruikt in `createRun()` helper |
| `ClaudeController::beginSseResponse()` | `yii/controllers/ClaudeController.php:501-513` | Hergebruikt in `actionStreamRun()` |
| `TimestampTrait` | `yii/models/traits/TimestampTrait.php` | Hergebruikt in `ClaudeRun` model |
| `NoteOwnerRule` pattern | `yii/rbac/NoteOwnerRule.php` | Pattern gevolgd voor `ClaudeRunOwnerRule` |
| RBAC config structure | `yii/config/rbac.php` | Uitgebreid met `claudeRun` entity |
| `storage/projects/` directory | `yii/storage/` | Zelfde storage pattern voor `storage/claude-runs/` |
| Docker `pma_yii` service | `docker-compose.yml` | `pma_queue` hergebruikt dezelfde Dockerfile/volumes (minus nginx/xdebug) |

---

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| `actionStream` volledig vervangen door async flow | Twee paden = dubbele test-oppervlakte, geen UX-voordeel. Tijdelijk wrapper, daarna verwijderen |
| `yii2-queue` met DB driver | Standaard Yii2-patroon, ingebouwde retry/mutex, driver-agnostisch (later Redis). Poll interval 3s acceptabel — browser animatie maskeert latentie |
| Aparte Docker service `pma_queue` | Geheugen-isolatie, onafhankelijke restart policy, schaalbaar. ~80 MB idle op 64 GB machine: verwaarloosbaar |
| File-based stream relay (geen Redis) | Geen extra infra. Apple SSD: >3 GB/s read. Worker append-only, relay read-only. Zero-bottleneck |
| Cancel via DB status-poll (niet PID kill) | PID-based cancel werkt niet cross-container (aparte PID-namespaces). DB poll elke 10s is betrouwbaar |
| `stream_log` bulk write bij completion | Voorkomt DB-druk bij snelle stream events. File is primaire bron; DB is fallback na cleanup |
| `result_text` in Markdown formaat | Claude CLI output is altijd markdown. Geen conversie naar Quill Delta nodig — alleen voor background processing |
| Max 3 concurrent runs | Voorkomt misbruik. Vierde poging geeft HTTP 429. Configureerbaar via model constant |
| Atomaire status-transitie (`UPDATE WHERE status='pending'`) | Voorkomt dubbel oppakken door meerdere workers (future-proof) |

---

## Open vragen

- Geen op dit moment.

---

## UI/UX overwegingen

### Layout/Wireframe

De bestaande Claude chat UI (`yii/views/claude/index.php`) blijft grotendeels intact. Wijzigingen zijn minimaal:

```
┌────────────────────────────────────────────────────────────────┐
│  Claude Chat — Project X                    [Active Runs: 2]  │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌─ Exchange 1 (accordion) ──────────────────────────────────┐ │
│  │ User: "Implement feature X..."                             │ │
│  │ Claude: [streaming response / completed response]          │ │
│  │ [metadata: model, tokens, duration]                        │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                │
│  ┌─ Active Response ─────────────────────────────────────────┐ │
│  │ ● thinking...  [Cancel]                                    │ │
│  │ [streaming dots / partial response]                        │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                │
│  ┌─ Reconnect Banner (indien van toepassing) ────────────────┐ │
│  │ ℹ Reconnected to running inference — replaying events...   │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                │
│  ┌─ Prompt Editor ───────────────────────────────────────────┐ │
│  │ [Quill editor / textarea]                          [Send] │ │
│  └────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────┘
```

### Active Runs Badge

```
┌──────────────────┐
│ Active Runs: 2   │  ← Kleine badge naast project naam
└──────────────────┘
```

- Toont aantal `pending` + `running` runs voor dit project
- Verdwijnt wanneer er geen actieve runs zijn
- Geen dropdown/panel (buiten scope eerste iteratie)

### UI States

| State | Visueel | Locatie |
|-------|---------|---------|
| Loading (pagina) | Geen visuele blokkade — `active-runs` check is async en non-blocking | N/A |
| Waiting (queue delay) | "Starting inference..." in bestaande streaming placeholder (zelfde positie als "thinking..."). Pulserende dots | Active Response area |
| Streaming (running) | Identiek aan huidige streaming: thinking dots → "responding" → live tekst. Cancel knop zichtbaar | Active Response area |
| Reconnecting | Informatieve banner boven Active Response. Tekst: "Reconnected — replaying events..." Verdwijnt automatisch zodra replay klaar is en live stream aansluit | Boven Active Response |
| Completed | Resultaat met metadata (tokens, model, duur). Identiek aan huidige weergave | Accordion item |
| Failed | Foutmelding in rood alert: "Inference failed: {error_message}". Geen retry-knop (eerste iteratie) | Accordion item |
| Cancelled | Melding: "Inference cancelled." Partial output getoond in accordion indien beschikbaar | Accordion item |
| Max concurrent | Bootstrap alert (dismissible): "Maximum concurrent runs reached (3). Wait for a run to finish." | Boven prompt editor |
| Empty (geen runs) | Geen visuele wijziging t.o.v. huidige staat | N/A |
| Error (HTTP/netwerk) | Bestaande error handling: rode tekst onder editor | Onder editor |

### Reconnect gedrag (pagina herlaad)

Bij pagina-herlaad met een lopende of recent voltooide run:

1. **Running run**: Er wordt een nieuw accordion item aangemaakt met de prompt uit de run. De streaming placeholder wordt getoond, reconnect banner verschijnt, en de stream wordt geopend vanaf offset 0 (volledige replay). Events worden versneld afgespeeld (geen throttle op replay). Banner verdwijnt na catch-up.
2. **Recent completed run**: Het resultaat wordt getoond in een accordion item, inclusief metadata. De `sessionId` wordt hersteld zodat follow-up prompts aansluiten.
3. **Meerdere actieve runs**: Alleen de meest recente `running` run wordt automatisch aangehaakt. Overige runs zijn zichtbaar via de badge (aantal). Geen auto-switch tussen runs (buiten scope eerste iteratie).

### Accessibility

- Active runs badge: `aria-live="polite"` voor screenreader updates
- Cancel button: `aria-label="Cancel running inference"`
- Reconnect banner: `role="status"` met `aria-live="polite"`
- Streaming status: `aria-live="assertive"` voor "thinking" / "responding" / "completed"
- Keyboard: Send met Ctrl+Enter (bestaand), Cancel met Escape (nieuw)

---

## Technische overwegingen

### Backend

#### Nieuwe endpoints

| Endpoint | Method | Input | Output | Auth |
|----------|--------|-------|--------|------|
| `/claude/start-run?p={id}` | POST | `{prompt, contentDelta, model, permissionMode, sessionId}` | `{success, runId, promptMarkdown}` | Project ownership (matchCallback) |
| `/claude/stream-run?runId={id}&offset={n}` | GET | — | SSE stream | Run ownership (forUser query) |
| `/claude/cancel-run?runId={id}` | POST | — | `{success, cancelled}` | Run ownership (forUser query) |
| `/claude/run-status?runId={id}` | GET | — | `{success, id, status, sessionId, resultMetadata, errorMessage, startedAt, completedAt}` | Run ownership (forUser query) |
| `/claude/active-runs?p={id}` | GET | — | `{success, runs: [{id, status, promptSummary, sessionId, startedAt, createdAt}]}` | Project ownership (matchCallback) |

#### Queue configuratie

```php
// yii/config/main.php → components
'queue' => [
    'class' => \yii\queue\db\Queue::class,
    'db' => 'db',
    'tableName' => '{{%queue}}',
    'channel' => 'claude',
    'mutex' => \yii\mutex\MysqlMutex::class,
    'ttr' => 3900,      // 65 min (> max Claude timeout 3600s)
    'attempts' => 1,     // geen retry (inference niet idempotent)
],
```

#### Wijzigingen aan ClaudeCliService (4 stuks)

1. `connection_aborted()` guard: `if (PHP_SAPI !== 'cli' && connection_aborted())`
2. Verwijder null-fallback voor streamToken (null is valide vanuit job context)
3. Conditionele `storeProcessPid()`: alleen als `$streamToken !== null`
4. Conditionele `clearProcessPid()`: alleen als `$streamToken !== null`

#### Docker service: `pma_queue`

```yaml
pma_queue:
  restart: unless-stopped
  build:
    context: .
    dockerfile: ./docker/yii/Dockerfile
    args:
      - USER_ID=${USER_ID:-1000}
      - USER_NAME=${USER_NAME:-appuser}
      - PHP_FPM_PORT=${PHP_FPM_PORT:-9000}
  container_name: pma_queue
  working_dir: /var/www/html/yii
  mem_limit: 256m
  command: ["php", "yii", "queue/listen", "--verbose=1"]
  volumes:
    - .:/var/www/html
    - ${PROJECTS_ROOT}:/projects:rw
    - ${HOME}/.claude:/home/${USER_NAME}/.claude
    - ${HOME}/.claude-config:/home/${USER_NAME}/.claude-config
    - ${HOME}/.local/bin:/home/${USER_NAME}/.local/bin
    - ${HOME}/.local/share:/home/${USER_NAME}/.local/share
  env_file:
    - .env
    - path: .env.db
      required: false
  environment:
    HOME: /home/${USER_NAME}
    PATH: /home/${USER_NAME}/.local/bin:/var/www/html/yii:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
    TZ: ${TIMEZONE:-Europe/Amsterdam}
  depends_on:
    - pma_mysql
  networks:
    - promptmanager_network
```

### Frontend

#### Wijzigingen in `ClaudeChat` object (`yii/views/claude/index.php`)

1. **`send()` methode**: Twee-staps flow (POST start-run → GET stream-run)
2. **`connectToStream(runId, offset)`**: Nieuwe methode voor SSE relay met reconnect
3. **`cancelRun()`**: Cancel via runId i.p.v. streamToken
4. **`checkActiveRuns()`**: Nieuwe methode, aangeroepen bij `init()`
5. **`showActiveRunsBadge(count)`**: Badge met actieve run telling
6. **Nieuwe properties**: `currentRunId` (naast bestaande `sessionId`, `streamToken`)

#### Migratiestrategie frontend

Fase 1: `send()` roept nog steeds de bestaande `actionStream` URL aan (wrapper). Geen JS-wijziging nodig.

Fase 2: `send()` gebruikt `start-run` + `stream-run`. Bestaande stream event handlers (`onStreamEvent`, `onStreamDelta`, `onStreamResult`, etc.) blijven identiek — het SSE format is ongewijzigd.

---

## Test scenarios

### Unit tests

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| `ClaudeRun::isActive()` retourneert true voor pending/running | Status = `pending` of `running` | `true` |
| `ClaudeRun::isTerminal()` retourneert true voor completed/failed/cancelled | Status = `completed`, `failed`, `cancelled` | `true` |
| `ClaudeRun::markRunning()` zet status, PID en started_at | PID = 123 | status=running, pid=123, started_at ingevuld |
| `ClaudeRun::markCompleted()` zet resultaat en metadata | resultText, metadata array | status=completed, result_text ingevuld, pid=null |
| `ClaudeRun::markFailed()` zet error en completed_at | error string | status=failed, error_message ingevuld, pid=null |
| `ClaudeRun::markCancelled()` zet status en completed_at | — | status=cancelled, pid=null |
| `ClaudeRun::getStreamFilePath()` retourneert correct pad | id=42 | `@app/storage/claude-runs/42.ndjson` |
| `ClaudeRunQuery::active()` filtert op pending+running | Mix van statussen | Alleen pending en running records |
| `ClaudeRunQuery::forUser()` filtert op user_id | userId=1 | Alleen records met user_id=1 |
| `ClaudeRunQuery::forProject()` filtert op project_id | projectId=5 | Alleen records met project_id=5 |
| `ClaudeRunStatus::activeValues()` | — | `['pending', 'running']` |
| `ClaudeRunStatus::terminalValues()` | — | `['completed', 'failed', 'cancelled']` |
| `RunClaudeJob::execute()` skipt als run niet pending | Status = `running` | Geen actie |
| `RunClaudeJob::execute()` skipt als run niet gevonden | Niet-bestaand runId | Geen actie |
| `RunClaudeJob::canRetry()` retourneert false | — | `false` |
| `RunClaudeJob::getTtr()` retourneert 3900 | — | `3900` |
| `ClaudeStreamRelayService::relay()` leest vanaf offset | File met 3 regels, offset=0 | Alle 3 regels doorgegeven aan callback |
| `ClaudeStreamRelayService::relay()` stopt als run niet actief | `$isRunning` retourneert false | Stopt na huidige data gelezen |
| `ClaudeStreamRelayService::relay()` retourneert nieuwe offset | File van 100 bytes gelezen | Retourneert 100 |
| `ClaudeStreamRelayService::relay()` skipt lege regels | File met lege regels | Alleen niet-lege regels doorgegeven |
| `ClaudeStreamRelayService::relay()` handelt ontbrekend file | Niet-bestaand pad | Retourneert offset ongewijzigd |
| `CleanupStale` markeert oude runs als failed | Run met updated_at > 5 min geleden | status=failed, error_message ingevuld |
| `CleanupStale` negeert recente runs | Run met updated_at < 5 min geleden | Status ongewijzigd |
| `CleanupFiles` verwijdert oude stream-files | File ouder dan 24h | File verwijderd |
| `CleanupFiles` behoudt recente files | File jonger dan 24h | File behouden |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| Concurrency limiet bereikt | 3 actieve runs, 4e poging | HTTP 429 met melding |
| Atomaire status-transitie | 2 workers proberen dezelfde run | Slechts 1 slaagt (affected=1), andere stopt |
| Cancel op niet-actieve run | Run status = `completed` | `{ cancelled: false, reason: "Run is not active." }` |
| Stream relay met fallback naar DB | Stream-file verwijderd, `stream_log` in DB | Events uit DB verstuurd |
| SSE bij run die al klaar is | Run status = `completed`, stream-file bestaat | Alle events verstuurd + `[DONE]` |
| Reconnect met offset mid-stream | File met 10 events, offset na event 5 | Events 6-10 verstuurd |
| Worker crash zonder heartbeat | Process killed, geen `updated_at` update | Stale detector markeert als failed na 5 min |

---

## Validatie & Beveiliging

### RBAC

- `ClaudeRun` heeft `user_id` — alle queries filteren op eigenaar via `ClaudeRunQuery::forUser()`
- Nieuwe RBAC rule: `ClaudeRunOwnerRule` (controleert `user_id`)
- Project-gebaseerde endpoints (`start-run`, `active-runs`): ownership via bestaande `matchCallback` + `findProject()`
- Run-gebaseerde endpoints (`stream-run`, `cancel-run`, `run-status`): ownership via `forUser()` query
- RBAC config in `yii/config/rbac.php`: `claudeRun` entity toevoegen

### CSRF-bescherming

- Alle POST endpoints (`start-run`, `cancel-run`) vereisen `X-CSRF-Token` header (standaard Yii2 `VerbFilter` + frontend `yii.getCsrfToken()`)
- GET endpoints (`stream-run`, `run-status`, `active-runs`) zijn idempotent en vereisen geen CSRF

### Input validatie

- `prompt_markdown`: verplicht, niet leeg (bestaande `prepareClaudeRequest()` validatie)
- `project_id`: moet bestaan en eigendom zijn van user (bestaande `findProject()`)
- `options`: whitelist van keys via `$allowedKeys` array: `['model', 'permissionMode', 'appendSystemPrompt', 'allowedTools', 'disallowedTools']`. Keys buiten deze whitelist worden genegeerd. Waarden worden gevalideerd door de bestaande `prepareClaudeRequest()` logica.
- `runId`: integer, moet bestaan en eigendom zijn van user
- `offset`: non-negative integer (default 0)

### Data-isolatie

- `stream_log`, `result_text` en `result_metadata` kunnen gevoelige projectdata bevatten (code, bestandsinhoud)
- Alleen de eigenaar (`user_id`) heeft via queries toegang tot deze data
- Stream-files op disk (`storage/claude-runs/{id}.ndjson`) zijn alleen leesbaar via het SSE endpoint met ownership check — niet direct via web
- De `storage/claude-runs/` directory is NIET publiek toegankelijk (buiten webroot)

### Concurrency limiet

- Max 3 `running`+`pending` runs per user (tel bij `start-run`)
- HTTP 429 met duidelijke melding
- Voorkomt resource exhaustion (DoS) door excessieve parallel inference

### Cleanup

- Stream-files: verwijderd na 24h via cron (`claude-run/cleanup-files`)
- `stream_log` in DB: bewaard (geen auto-cleanup — records zijn klein na file verwijdering)
- `ClaudeRun` records: bewaard permanent voor audit

---

## Scope-afbakening

### In scope

- Nieuwe `claude_run` tabel + model + migration
- Background worker met `yii2-queue` (DB driver), apart Docker service
- File-based stream relay met SSE endpoint
- Start/cancel/status/active-runs endpoints
- Frontend: twee-staps flow (start + stream), reconnect, cancel via runId
- Active runs badge
- Migratie wrapper voor `actionStream`
- Stale-run cleanup command + file cleanup command
- Concurrency limiet (max 3)
- RBAC rule + config
- Unit tests voor model, query, job, relay service, cleanup commands, enum

### Buiten scope (toekomstig)

- Webhook/notificatie bij voltooiing (Slack, email)
- Run scheduling (uitgestelde start)
- Run prioritering
- Shared runs (andere user mag meekijken)
- Export van run-history
- Redis queue driver (start met DB driver)
- Run retry mechanisme
- Runs overzicht pagina (dropdown/panel met lijst)
- Verwijdering van oude `actionStream`/`actionRun`/`actionCancel` (fase 2, na validatie)

---

## Migratie-impact

### Database
- Nieuwe tabel: `claude_run` (17 kolommen)
- Nieuwe tabel: `queue` (yii2-queue DB driver eigen migratie)
- Geen wijzigingen aan bestaande tabellen

### Infra
- `composer require yiisoft/yii2-queue` (DB driver)
- Nieuw Docker service `pma_queue` in `docker-compose.yml`
- Cron job voor stale-run cleanup (elke 5 min, vanuit `pma_yii`)
- Cron job voor file cleanup (dagelijks, vanuit `pma_yii`)
- Storage directory: `yii/storage/claude-runs/` (gedeeld volume)
- Geheugenimpact: ~80 MB idle, max 256 MB (cap)

### Risico's

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| yii2-queue DB poll interval (3s) | Lichte vertraging bij run start | Browser animatie maskeert latentie |
| Cross-container PID kill werkt niet | Cancel via SIGTERM faalt | Cancel via DB status-poll (elke 10s) |
| Worker OOM | Run blijft hangen | `mem_limit: 256m` + heartbeat + stale detector |
| Docker Desktop macOS file I/O | Langzamere stream relay | VirtioFS + Apple SSD compenseert |
| Stream-file groeit bij lange runs | Disk gebruik | Cleanup cron (24h) |
| Race: twee workers pakken dezelfde job | Dubbele execution | Atomaire UPDATE + yii2-queue mutex |
| Worker draait verouderde code na deploy | Nieuwe features niet actief | `docker restart pma_queue` na deploy |

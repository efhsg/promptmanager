# Spec Reviews — Async Claude Inference

## Review: Architect — 2026-02-15

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe/layout komt overeen met component beschrijvingen
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Componenttabel is compleet met type, locatie en wijzigingstype — makkelijk om implementatie te plannen
- Architectuurbeslissingen zijn helder en met rationale onderbouwd (file-based relay, DB queue driver, cancel via poll)
- Herbruikbare componenten zijn concreet geïdentificeerd met regelnummers (bijv. `ClaudeCliService::executeStreaming()` L205-298)
- Statusovergangen zijn duidelijk gedefinieerd met atomaire transitie-patroon
- Alle nieuwe componenten volgen bestaande codebase patronen (model/query/enum/RBAC rule)
- Domeinmodel is grondig met indices en rationale
- Edge cases dekken realistische scenario's (worker crash, PID hergebruik, cross-container permissies)

### Verbeterd
- Geen wijzigingen nodig. De spec is architecturaal compleet.

### Opmerkingen
- **Minor**: `RunClaudeJob` (225 regels) bevat stream-parsing logica (extractResultText, extractMetadata, extractSessionId) die potentieel naar een aparte service kan. Dit is een toekomstige refactoring-suggestie, geen spec-probleem.
- **Positief**: De keuze voor file-based stream relay zonder Redis is pragmatisch en past bij de Docker Desktop + Apple SSD context.
- **Positief**: De migratiestrategie (wrapper → volledige vervanging) minimaliseert risico.

### Nog open
- Geen

---

## Review: Security — 2026-02-15

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe/layout komt overeen met component beschrijvingen
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- **Toegangscontrole**: Elke endpoint is owner-scoped via `ClaudeRunQuery::forUser()` — geen cross-user data leakage mogelijk
- **Input validatie**: PHP type hints op alle action parameters (`int $runId`, `int $offset`), options whitelist via `array_intersect_key()`, CSRF actief op alle POST endpoints
- **Data exposure**: Minimale response payloads — `run-status` bevat geen `prompt_markdown`, `result_text`, of `working_directory`. Alleen metadata en identifiers
- **File system**: Stream files buiten webroot (`yii/storage/`), paden hardcoded met alleen `$this->id` (auto-increment integer), geen path traversal vector
- **RBAC**: `ClaudeRunOwnerRule` volgt exact het NoteOwnerRule patroon. Config in `rbac.php` bevat `viewClaudeRun` en `updateClaudeRun` permissions
- **Concurrency limiet**: Max 3 runs voorkomt resource exhaustion (DoS via excessieve parallel inference)
- **Cleanup**: Stream-files buiten webroot, verwijderd na 24h, `stream_log` in DB voor audit

### Verbeterd
- Geen wijzigingen nodig. Security controls zijn compleet gespecificeerd.

### Opmerkingen
- **Minor inconsistentie**: Async run endpoints gebruiken query-level scoping (`forUser()`) i.p.v. expliciete RBAC permission checks in `behaviors()` zoals andere entiteiten. Functioneel veilig maar inconsistent met het patroon van bijv. `NoteController`.
- **Positief**: Defense-in-depth: authenticatie (`@` role) + query scopes + RBAC rules op meerdere lagen
- **Positief**: `options` whitelist is strikt — alleen 5 keys toegestaan, rest genegeerd

### Nog open
- Geen

---

## Review: UX/UI Designer — 2026-02-15

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe/layout komt overeen met component beschrijvingen
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- **UI States**: 10 distincte states gespecificeerd (loading, waiting, streaming, reconnecting, completed, failed, cancelled, max concurrent, empty, error) — uitstekende dekking
- **Wireframe**: ASCII wireframe geeft helder beeld van de layout met Active Runs badge, Active Response area, Reconnect Banner en Prompt Editor
- **Accessibility**: Concrete ARIA specificaties per component (`aria-live`, `role="status"`, `aria-label`)
- **Reconnect gedrag**: Drie scenario's uitgewerkt (running, completed, meerdere runs) met duidelijk verwacht gedrag
- **Bootstrap 5 adherence**: Implementatie volgt bestaande component patronen (cards, badges, alerts, modals)
- **Status kleurcodering**: Consistent systeem (success=completed, warning=pending, danger=failed, secondary=cancelled)
- **Streaming indicators**: Meervoudige visuele cues (dots, timer, status label, pulse animatie)

### Verbeterd
- Geen spec-wijzigingen nodig. De UI specificatie is compleet en consistent.

### Opmerkingen
- **Suggestie voor toekomstige iteratie**: Runs overzicht empty state is minimaal ("No runs yet.") — overweeg een informatieve empty state met icoon en CTA naar Claude chat
- **Suggestie voor toekomstige iteratie**: Cancel actie heeft geen bevestigingsdialog — bij lang lopende inferentie zou dit gebruikers beschermen
- **Positief**: De keuze om alleen de meest recente running run automatisch aan te haken is pragmatisch en voorkomt UI-complexiteit
- **Positief**: Bottom nav hiding op chat-pagina voorkomt conflicten met de sticky input

### Nog open
- Geen

---

## Review: Front-end Developer — 2026-02-15

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe/layout komt overeen met component beschrijvingen
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- **Stream relay**: Fetch API + ReadableStream geeft meer controle dan EventSource voor reconnect-scenario's
- **Cleanup**: Consistente cleanup in meerdere paden (end, error, cancel) — reader, timers, UI elementen
- **Auto-reconnect**: Max 3 pogingen, visibility change detection, sessie-herstel vanuit completed runs
- **Cancel**: Dual-path cancel (async runId + legacy streamToken) voor backward compatibility
- **State guards**: `streamEnded` flag voorkomt dubbele end-handling, `reconnectAttempts` limiteert reconnect loops
- **Bootstrap 5**: Correct gebruik van modals (`getOrCreateInstance`), collapse, alerts, badges
- **Mobile**: Auto-switch naar textarea op < 768px, keyboard detection voor bottom nav

### Verbeterd
- Geen spec-wijzigingen nodig. De frontend specificatie beschrijft de flow en componenten voldoende.

### Opmerkingen
- **Suggestie**: Spec noemt geen stream timeout — overweeg een max stream duration (bijv. 65 min, gelijk aan TTR) met reconnect optie
- **Suggestie**: HTTP 429 (rate limit) zou een specifieke melding moeten tonen i.p.v. generic error — spec vermeldt de max concurrent runs melding, maar de frontend implementatie toont "HTTP 429" als raw error
- **Positief**: De migratiestrategie (wrapper eerst) zorgt ervoor dat bestaande event handlers (`onStreamEvent`, `onStreamDelta`, etc.) ongewijzigd blijven
- **Positief**: `currentRunId` wordt mid-stream gezet vanuit `prompt_markdown` event — geen extra round-trip nodig

### Nog open
- Geen

---

## Review: Developer — 2026-02-15

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe/layout komt overeen met component beschrijvingen
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- **Architectuur**: Uitstekende separation of concerns — controller handelt HTTP, services handelen logica, models handelen data
- **DI**: Constructor-injectie in ClaudeController (`EntityPermissionService`, `ClaudeCliService`, `ClaudeQuickHandler`). `RunClaudeJob` gebruikt factory method patroon (`createCliService()`) voor testbaarheid
- **Atomaire operaties**: `claimForProcessing()` met `UPDATE WHERE status='pending'` voorkomt race conditions bij meerdere workers
- **Heartbeat**: Elke 30s heartbeat + cancellation check in de job callback — elegante combinatie van twee functies
- **Error handling**: Geen silent failures — alle exceptions worden gelogd of doorgegooid. Job vangt cancellation vs failure apart af
- **Migratiestrategie**: `actionStream` wrapper maakt Phase 1 transparant voor de frontend — backward compatible
- **DB fallback**: `actionStreamRun` valt terug op `stream_log` in DB als stream-file ontbreekt — cross-container resilience
- **Config**: Queue component met TTR=3900 (> Claude timeout), attempts=1, MySQL mutex, dedicated 'claude' channel
- **Coding standards**: Volledige type hints, PSR-12, geen `declare(strict_types=1)`, geen onnodige accolades

### Verbeterd
- Geen spec-wijzigingen nodig. De technische specificatie is implementatie-klaar.

### Opmerkingen
- **Minor**: `ClaudeStreamRelayService` wordt direct geïnstantieerd in controller i.p.v. via DI. Acceptabel (geen dependencies), maar inconsistent met rest van de controller
- **Minor**: `Yii::$app->cache` direct access in `ClaudeCliService` — testbaarheid zou verbeteren met geïnjecteerde cache component
- **Positief**: Session lock release in `beforeAction()` voor long-running SSE actions is essentieel en goed gedocumenteerd
- **Positief**: `clearstatcache()` gebruik bij file existence checks voorkomt PHP stat cache false negatives

### Nog open
- Geen

---

## Review: Tester — 2026-02-15

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe/layout komt overeen met component beschrijvingen
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- **Spec testbaarheid**: Alle 25 unit test scenarios in de spec zijn meetbaar en automatiseerbaar — elke rij in de test-tabel heeft input, verwacht resultaat, en is als Codeception test te schrijven
- **Edge case specificatie**: 7 edge case tests gespecificeerd met scenario, conditie en verwacht gedrag — geen "werkt correct" ambiguïteit
- **Model/Query/Service dekking**: Bestaande tests dekken 100% van de gespecificeerde unit test scenarios (25/25)
- **Test naming**: Alle tests volgen `test{Action}{Condition}` of `test{Action}When{Scenario}` patroon — consistent en leesbaar
- **Assertions**: Betekenisvolle assertions — verifiëren specifieke waarden (status, timestamps, counts), niet alleen "gooit geen exception"
- **Cleanup tests**: Stale run detectie en file cleanup beide getest met threshold-gebaseerde verificatie

### Verbeterd
- Geen spec-wijzigingen nodig. De test scenarios zijn specifiek en meetbaar genoeg.

### Opmerkingen
- **Implementatie-gap (niet spec)**: Controller endpoints zijn niet getest — `actionStartRun`, `actionStreamRun`, `actionCancelRun`, `actionRunStatus`, `actionActiveRuns`, `actionRuns` missen alle unit tests. De spec specificeert deze scenarios voldoende, maar de implementatie heeft de tests nog niet
- **Implementatie-gap (niet spec)**: Edge case test dekking is 21% (1.5/7) — concurrency limit (429), atomaire transitie, cancel op niet-actieve run, en DB fallback zijn gespecificeerd maar nog niet geïmplementeerd als tests
- **Positief**: De spec's test tabellen zijn direct om te zetten naar Codeception tests — input/output pairs zijn duidelijk
- **Suggestie**: Overweeg een `ClaudeControllerAsyncTest.php` specifiek voor de async endpoints, gescheiden van de bestaande tests

### Nog open
- Geen

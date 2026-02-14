# Insights — Async Claude Inference

## Codebase onderzoek

### Vergelijkbare features
- `ClaudeController::actionStream()` (`yii/controllers/ClaudeController.php:193-253`) — huidige synchrone SSE streaming implementatie. Dit is het primaire target voor vervanging.
- `ClaudeController::actionRun()` (`yii/controllers/ClaudeController.php:148-188`) — blocking JSON response implementatie. Wordt ook vervangen.
- `ClaudeController::actionCancel()` (`yii/controllers/ClaudeController.php:258-279`) — cancel via PID cache + streamToken. Wordt vervangen door DB-gebaseerde cancel.
- `ClaudeCliService::executeStreaming()` (`yii/services/ClaudeCliService.php:205-298`) — core proc_open streaming met `$onLine` callback. Wordt hergebruikt door de job met aanpassingen.
- `ClaudeCliService::cancelRunningProcess()` (`yii/services/ClaudeCliService.php:306-332`) — PID-based cancel via posix_kill. Werkt niet cross-container, vervangen door DB status-poll.

### Herbruikbare componenten
- `ClaudeCliService::executeStreaming()` — volledig herbruikbaar vanuit RunClaudeJob, met 4 aanpassingen (CLI guard, null streamToken, conditionele PID store/clear)
- `ClaudeCliService::buildCommand()` — ongewijzigd herbruikbaar
- `ClaudeController::prepareClaudeRequest()` — herbruikbaar voor createRun() helper
- `ClaudeController::beginSseResponse()` — herbruikbaar voor actionStreamRun()
- `TimestampTrait` — herbruikbaar voor ClaudeRun model
- `ClaudeWorkspaceService` — workspace resolution al ingebouwd in ClaudeCliService
- `ClaudeQuickHandler` — niet gerelateerd, blijft ongewijzigd
- Bestaande RBAC patterns (NoteOwnerRule, rbac.php) — te volgen voor ClaudeRunOwnerRule
- `yii/storage/projects/` — storage directory pattern al beschikbaar voor `storage/claude-runs/`

### Te volgen patterns
- Enum pattern: `yii/common/enums/CopyType.php`, `ClaudePermissionMode.php` — voor ClaudeRunStatus
- Model + Query pattern: `yii/models/Note.php` + `yii/models/query/NoteQuery.php` — voor ClaudeRun
- TimestampTrait: `yii/models/traits/TimestampTrait.php` — handleTimestamps() in beforeSave
- RBAC config: `yii/config/rbac.php` — toevoegen claude entity permissions
- Docker service pattern: `pma_yii` in `docker-compose.yml` — zelfde Dockerfile, andere command
- Console command: `yii/commands/ClaudeController.php` — bestaand, nieuwe `ClaudeRunController.php` ernaast
- SSE pattern: bestaande `beginSseResponse()` + `data: ... \n\n` flush

### Bestaande plan.md analyse
- Er bestaat al een uitgebreid technisch plan in `.claude/design/feature/async-inference/plan.md`
- Plan bevat model code, job code, controller actions, frontend JS, migration SQL
- Plan is goed uitgewerkt en consistent met codebase patterns
- Plan mist UI wireframes, accessibility overwegingen en sommige edge cases in spec.md

## Beslissingen
- Architect: `actionRunHistory` verwijderd uit scope (was inconsistent)
- Architect: Docker `pma_queue` depends_on gecorrigeerd naar `pma_mysql`
- Security: CSRF-bescherming expliciet gedocumenteerd voor POST endpoints
- Security: Options whitelist exact gespecificeerd (5 keys)
- Security: Data-isolatie sectie toegevoegd voor gevoelige data
- UX/UI: UI states tabel uitgebreid met "Locatie" kolom
- UX/UI: Reconnect gedrag bij pagina-herlaad gedetailleerd (3 scenario's)
- UX/UI: Non-blocking page load, geen retry-knop in eerste iteratie

## Consistentiecheck (5/5 PASS)
1. Wireframe ↔ Components: PASS
2. Frontend ↔ Backend: PASS
3. Edge cases ↔ Tests: PASS
4. Architecture ↔ Locations: PASS
5. Security ↔ Endpoints: PASS

## Open vragen
_(Geen — alle vragen beantwoord tijdens reviews)_

## Blokkades
_(Geen)_

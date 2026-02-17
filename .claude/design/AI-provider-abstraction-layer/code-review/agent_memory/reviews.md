# Review Resultaten — Ronde 2

## Review: Reviewer — 2026-02-17

### Score: 7/10

### Goed
- Interface-segregatie: ISP correct — 1 verplichte + 4 optionele interfaces
- Owner-scoping: alle AiRun queries via forUser()
- RBAC: entity keys en permissions correct bijgewerkt
- DI-patronen: constructor injection consequent
- Migraties, queue backward-compat, URL backward-compat correct

### Wijzigingen doorgevoerd
- `EntityPermissionService::MODEL_BASED_ACTIONS`: oude claude-actienamen verwijderd (dood gewicht: `run-claude`, `stream-claude`, `cancel-claude`, `check-claude-config`, `claude-commands`, `claude-usage`)
- `ProjectController::loadAiOptions()`: `$claudeOptions` → `$aiOptions`
- `AiQuickHandler`: log label `"ClaudeQuick"` → `"AiQuick"`
- `AiRunController`: stdout `"stale Claude runs"` → `"stale AI runs"`
- `RunAiJob`: error string `"Claude CLI exited"` → `"AI CLI exited"`
- `actionClaude()` bewust niet hernoemd — is backward-compat redirect

### Testresultaat
1010 tests, 0 failures

## Review: Architect — 2026-02-17

### Score: 8/10

### Goed
- Interface-hiërarchie: ISP correct, goed gedocumenteerd met @see referenties
- Folder-structuur: services/ai/ voor interfaces, services/ai/providers/ voor implementaties
- DI-registratie minimaal: alleen AiProviderInterface geregistreerd
- Query-logica consequent in AiRunQuery
- Project::afterSave()/afterDelete(): DI-resolutie + instanceof
- RunAiJob factory methods voor testbaarheid

### Wijzigingen doorgevoerd
- RunAiJob docblock: `"Claude CLI inference run"` → `"AI CLI inference run"`
- RunAiJob comment: `"max Claude timeout"` → `"max AI run timeout"`

### Nog open (follow-up)
- ClaudeCliProvider 1123 regels — splitsing bij toekomstige uitbreiding

## Review: Security — 2026-02-17

### Score: 9/10

### Goed
- Owner-scoping: alle AiRun queries via forUser()
- RBAC: AiRunOwnerRule met type-coercion
- VerbFilter op alle muterende endpoints
- escapeshellarg() consequent in buildCommand()
- UUID regex validatie op stream tokens
- Provider identifier match pattern op AiRun model
- PID cache per-user key voorkomt cross-user process kill
- Credential path via server-side config
- Stream file via integer PK — geen path traversal

### Wijzigingen doorgevoerd
Geen — security-implementatie is compleet en correct

## Review: Front-end Developer — 2026-02-17

### Score: 8/10

### Goed
- URLs correct bijgewerkt naar /ai-chat/
- CSS-bestand hernoemd naar ai-chat.css
- View directory hernoemd van views/claude/ naar views/ai-chat/
- sessionStorage keys correct hernoemd met fallback
- CSS-klassen terecht niet hernoemd (FR-7)
- Json::encode() correct gebruikt voor JS-variabelen

### Wijzigingen doorgevoerd
- `note/view.php`: `$canRunClaude` → `$canRunAi`, `$claudeTooltip` → `$aiTooltip`
- `prompt-instance/view.php`: `$canRunClaude` → `$canRunAi`, `$claudeTooltip` → `$aiTooltip`
- `note/index.php`: GridView template `{claude}` → `{ai}`, array key hernoemd
- `prompt-instance/index.php`: GridView template `{claude}` → `{ai}`, array key hernoemd

### Testresultaat
1011 tests, 0 failures

## Review: Developer — 2026-02-17

### Score: 8/10

### Goed
- Constructor injection consequent in AiChatController (6 dependencies)
- AiRun model: atomaire claimForProcessing() via updateAll met status-guard
- RunAiJob: lineaire flow, heartbeat + cancellation check elke 30s
- Stream file lifecycle correct: open → write → flush → close → [DONE] append
- Protected factory methods voor testbaarheid
- @file_get_contents met comment waarom error suppression nodig is
- writeDoneMarker sequencing voorkomt race condition in SSE relay
- buildSessionDialog trunceert van begin zodat recente context behouden blijft

### Wijzigingen doorgevoerd
Geen — implementatie is clean en consistent

### Nog open (follow-up)
- NDJSON parsing duplicatie RunAiJob vs ClaudeCliProvider::parseStreamResult() — refactoring als apart PR

## Review: Tester — 2026-02-17

### Score: 8/10

### Goed
- Bestaande testsuite: 12 tests in RunAiJobTest dekken happy path, failure, retryable, TTR, [DONE] marker, session summary, stream file cleanup
- Factory-method patroon maakt mocking effectief
- Fixture-based DB state via AiRunFixture

### Test-gaps gevonden
1. **Cancellation flow** — geen test voor cancel mid-run
2. **Session ID extractie** — geen test voor extractSessionId (system- en result-type lines)
3. **Fallback error message** — bestaande test gebruikt provider error, raakt nooit de `'AI CLI exited with code X'` fallback
4. **EntityPermissionService MODEL_BASED_ACTIONS** — geen regressietest (niet geïmplementeerd, advies)

### Tests toegevoegd (4 methodes)
- `testCancellationMidRunMarksRunAsCancelled` — exception path verificatie + [DONE] marker
- `testSessionIdExtractedFromResultLine` — session_id uit result-type NDJSON line
- `testSessionIdExtractedFromSystemLine` — session_id uit system-type line (heeft prioriteit)
- `testFallbackErrorMessageWhenProviderErrorIsEmpty` — fallback 'AI CLI exited with code 42'

### Design-notitie
Cancellation test is pragmatisch: de mock-boundary voorkomt dat de interne `$onLine` callback de `$run->refresh()` uitvoert die de CANCELLED status detecteert. Test verifieert de exception-handling path en [DONE] marker, niet de volledige cancellation flow.

### Testresultaat
16 tests, 42 assertions, 0 failures

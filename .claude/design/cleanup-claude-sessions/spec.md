# Feature: Cleanup Claude Sessions

## Samenvatting
Voeg cleanup-functionaliteit toe aan de Claude Sessions pagina (`/claude/runs`) waarmee gebruikers individuele sessies of alle voltooide sessies tegelijk kunnen verwijderen, inclusief bijbehorende stream-bestanden.

## User story
In claude/runs we need a cleanup function, so the user can easily remove dialogs that are no longer needed.

## Functionele requirements

### FR-1: Individuele sessie verwijderen
- Beschrijving: De gebruiker kan een enkele sessie verwijderen via een delete-knop in de sessie-rij van het GridView.
- Acceptatiecriteria:
  - [ ] Elke sessie-rij toont een delete-knop (trash icon)
  - [ ] Klikken op de delete-knop toont een bevestigingsdialoog (JavaScript `data-confirm`)
  - [ ] Na bevestiging worden alle ClaudeRun records met dezelfde `session_id` verwijderd
  - [ ] Bij een standalone run (geen `session_id`) wordt enkel dat record verwijderd
  - [ ] Bijbehorende `.ndjson` stream-bestanden worden verwijderd
  - [ ] Alleen terminal sessies (completed, failed, cancelled) zijn verwijderbaar
  - [ ] Actieve sessies (pending, running) tonen geen delete-knop
  - [ ] Na verwijdering keert de gebruiker terug naar de runs-pagina met een success flash message

### FR-2: Bulk cleanup (alle terminal sessies verwijderen)
- Beschrijving: De gebruiker kan in één actie alle voltooide/mislukte/geannuleerde sessies opruimen via een "Cleanup all" knop.
- Acceptatiecriteria:
  - [ ] Een "Cleanup" knop is zichtbaar in de card-header naast de zoekfilters
  - [ ] Klikken toont een bevestigingspagina met het aantal te verwijderen sessies en het totaal aantal runs
  - [ ] Na bevestiging worden alle terminal ClaudeRun records van de gebruiker verwijderd
  - [ ] Bijbehorende `.ndjson` stream-bestanden worden verwijderd
  - [ ] Actieve runs (pending, running) worden NIET verwijderd
  - [ ] Na verwijdering keert de gebruiker terug naar de runs-pagina met een success flash message

### FR-3: Eigendomsvalidatie
- Beschrijving: Alleen de eigenaar van een sessie kan deze verwijderen.
- Acceptatiecriteria:
  - [ ] Delete-acties worden beveiligd via `ClaudeRunQuery::forUser()` scope (consistent met bestaande run-endpoints)
  - [ ] Controller access control via `'roles' => ['@']` (authenticated users), ownership via query scope
  - [ ] Ongeautoriseerde delete-pogingen resulteren in een 404 (run niet gevonden)
  - [ ] Geen aparte RBAC permission nodig — volgt patroon van bestaande `runs`, `stream-run`, `cancel-run` endpoints

## Gebruikersflow

### Single delete
1. Gebruiker navigeert naar `/claude/runs`
2. Gebruiker ziet een lijst van sessies met per rij een delete-icoon (alleen bij terminal sessies)
3. Gebruiker klikt op het delete-icoon
4. Browser toont JavaScript confirm-dialoog: "Delete this session? (X runs will be removed)"
5. Gebruiker bevestigt
6. Systeem verwijdert alle runs van de sessie + stream files
7. Pagina herlaadt met success flash message

### Bulk cleanup
1. Gebruiker navigeert naar `/claude/runs`
2. Gebruiker klikt op "Cleanup" knop in de card-header
3. Systeem toont bevestigingspagina met aantal te verwijderen sessies
4. Gebruiker klikt "Yes, Delete All"
5. Systeem verwijdert alle terminal runs + stream files
6. Redirect naar `/claude/runs` met success flash message

## Edge cases

| Case | Gedrag |
|------|--------|
| Sessie heeft mix van terminal en actieve runs | Alleen terminal runs in sessie worden verwijderd; actieve runs blijven bestaan |
| Gebruiker verwijdert sessie terwijl een run nog bezig is | Actieve runs worden niet verwijderd — delete-knop is verborgen voor actieve sessies |
| Stream file bestaat niet (al opgeruimd) | Gracefully skip — geen error |
| Bulk cleanup terwijl er geen terminal sessies zijn | Bevestigingspagina toont "0 sessions" — knop is disabled |
| Concurrent delete (twee tabs) | Tweede delete vindt geen records — geen error, redirect met melding |
| Gebruiker probeert andermans sessie te verwijderen | 404 Not Found (via `forUser()` scope) |

## Entiteiten en relaties

### Bestaande entiteiten
- **ClaudeRun** (`yii/models/ClaudeRun.php`) — `id`, `user_id`, `project_id`, `session_id`, `status`, stream file via `getStreamFilePath()`
- **ClaudeRunQuery** (`yii/models/query/ClaudeRunQuery.php`) — `forUser()`, `terminal()`, `forSession()`
- **ClaudeRunSearch** (`yii/models/ClaudeRunSearch.php`) — search/filter voor GridView
- **ClaudeRunOwnerRule** (`yii/rbac/ClaudeRunOwnerRule.php`) — eigendomsvalidatie

### Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| ClaudeRunCleanupService | Service | `yii/services/ClaudeRunCleanupService.php` | Nieuw: deleteSession(), bulkCleanup(), countTerminalSessions(), countTerminalRuns() |
| ClaudeController | Controller | `yii/controllers/ClaudeController.php` | Wijzigen: actionDeleteSession(), actionCleanup() toevoegen; VerbFilter + access control updaten; ClaudeRunCleanupService injecteren via constructor |
| runs.php | View | `yii/views/claude/runs.php` | Wijzigen: delete-kolom + cleanup-knop toevoegen |
| cleanup-confirm.php | View | `yii/views/claude/cleanup-confirm.php` | Nieuw: bevestigingspagina voor bulk cleanup |
| ClaudeRunCleanupServiceTest | Test | `yii/tests/unit/services/ClaudeRunCleanupServiceTest.php` | Nieuw: unit tests voor delete/cleanup logic |

**DI registratie:** Niet nodig in `config/main.php`. ClaudeController-services (ClaudeCliService, ClaudeStreamRelayService, etc.) worden auto-wired door Yii2's DI container via constructor type hints. ClaudeRunCleanupService volgt hetzelfde patroon — geen configuratie-wijziging nodig.

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| Delete confirmation card pattern | `yii/views/project/delete-confirm.php` | Template voor `cleanup-confirm.php` |
| `data-confirm` + `data-method` inline delete | `yii/widgets/MobileCardView.php` | Pattern voor single delete in GridView |
| `ClaudeRunQuery::forUser()` | `yii/models/query/ClaudeRunQuery.php` | Eigendomsfiltering bij delete |
| `ClaudeRunQuery::terminal()` | `yii/models/query/ClaudeRunQuery.php` | Filter voor verwijderbare runs |
| Flash message pattern | Alle controllers | Success/error feedback na delete |

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| `ClaudeRunCleanupService` (niet `ClaudeRunService`) | Naam beschrijft verantwoordelijkheid (cleanup/deletion), volgt `architecture.md` naamgevingsregel |
| Service-layer voor delete logic | Volgt bestaand patroon (FieldService, ContextService); houdt controller thin |
| Inline `data-confirm` voor single delete | Consistent met MobileCardView delete pattern; geen extra pagina nodig voor één sessie |
| Aparte bevestigingspagina voor bulk cleanup | Bulk operatie is destructief; volgt patroon van ProjectController::actionDelete |
| Delete op sessie-niveau (alle runs met zelfde session_id) | Matcht de UI-groupering in runs.php die session representatives toont |
| Geen soft-delete | ClaudeRun data is ephemeral (CLI output); hard delete is voldoende |
| Stream file cleanup in service | Centraliseert file I/O in service; controller hoeft niet te weten van filesystem |
| Ownership via query scope, geen RBAC permission | Consistent met bestaande run-endpoints die `forUser()` gebruiken, niet `EntityPermissionService` |
| Transactie rond DB deletes, files eerst | Stream files zijn idempotent te verwijderen; DB integriteit is kritischer |
| Endpoint `delete-session` (niet `delete-run`) | Naam matcht de UI-entiteit (sessie = groep runs) |

## Open vragen
- Geen

## UI/UX overwegingen

### Layout/Wireframe

```
┌──────────────────────────────────────────────────────────────┐
│  Claude Sessions                             [+ New dialog]  │
├──────────────────────────────────────────────────────────────┤
│  All Sessions                                                │
│  [Search___] [Status ▼] [Search] [Reset] [↻ Auto] [Cleanup] │
├──────┬─────────┬──────────────┬─────┬─────┬──────┬───────────┤
│Status│ Project │ Summary      │Runs │Start│ Dur. │           │
├──────┼─────────┼──────────────┼─────┼─────┼──────┼───────────┤
│ ✓    │ MyProj  │ Fix the bug  │  3  │ ... │ 2m   │    [🗑]   │
│ ✗    │ MyProj  │ Add feature  │  1  │ ... │ 1m   │    [🗑]   │
│ ⏳   │ MyProj  │ Running task │  2  │ ... │  -   │           │
└──────┴─────────┴──────────────┴─────┴─────┴──────┴───────────┘
```

> **Note:** Kolommen matchen de bestaande runs.php GridView (Status, Project, Summary, Runs, Started, Duration). Er is géén Cost-kolom in de huidige implementatie.

### UI States

| State | Visueel |
|-------|---------|
| Loading | Standaard Yii2 GridView loading (geen custom state nodig) |
| Empty | "No sessions yet." (bestaande lege GridView tekst) |
| Error | Flash message `alert-danger` bovenaan pagina |
| Success | Flash message `alert-success` bovenaan pagina na verwijdering |
| No deletable sessions | Cleanup knop is `disabled` met tooltip "No completed sessions to clean up" |
| Active session (no delete) | Geen delete-icoon in de rij — lege cel |

### Interactiedetails
- Delete-knop in GridView rij gebruikt `event.stopPropagation()` om row-click navigatie te voorkomen (consistent met MobileCardView pattern)
- Delete-knop is `btn-sm btn-outline-danger` met trash icon — klein, niet-opdringerig
- Cleanup-knop is `btn-outline-danger` — visueel onderscheiden van zoekfilters als destructieve actie
- Confirm dialoog voor single delete toont aantal runs: "Delete this session? (X runs will be removed)"
- Cleanup bevestigingspagina toont zowel aantal sessies als totaal aantal runs

### Accessibility
- Delete-knop heeft `title="Delete session"` en `aria-label="Delete session"`
- Cleanup-knop heeft descriptive text, niet alleen een icoon
- `data-confirm` dialoog is native browser confirm — inherent accessible
- Focus returns to runs page after delete action

## Technische overwegingen

### Backend

**Endpoints:**

| Endpoint | Method | Beschrijving |
|----------|--------|-------------|
| `/claude/delete-session?id={runId}` | POST | Verwijdert een sessie (alle runs met zelfde session_id) via representative run ID |
| `/claude/cleanup` | GET/POST | GET: toont bevestigingspagina; POST met `confirm=1`: voert bulk delete uit |

**Controller wijzigingen (ClaudeController):**
- Constructor: `ClaudeRunCleanupService $cleanupService` toevoegen aan DI parameters
- VerbFilter: `'delete-session' => ['POST']` toevoegen (cleanup niet in VerbFilter — ondersteunt GET voor bevestigingspagina + POST voor uitvoering)
- Access control: `'delete-session'` en `'cleanup'` toevoegen aan `'roles' => ['@']` regel (naast `runs`)
- `beforeAction`: geen wijziging nodig (geen long-running actions)

**ClaudeRunCleanupService:**

```php
class ClaudeRunCleanupService
{
    /**
     * Verwijdert alle terminal runs met zelfde session_id (of enkel de run bij null session_id).
     * De representativeRun wordt gebruikt om session_id en user_id af te leiden.
     * Query scoped op forUser() + terminal() voor beveiliging.
     *
     * @return int aantal verwijderde records
     */
    public function deleteSession(ClaudeRun $representativeRun): int

    /**
     * Verwijdert alle terminal runs van de gebruiker.
     *
     * @return int aantal verwijderde records
     */
    public function bulkCleanup(int $userId): int

    /**
     * Telt het aantal verwijderbare sessies (voor bevestigingspagina).
     * Telt distinct session_id's + standalone runs (session_id IS NULL).
     */
    public function countTerminalSessions(int $userId): int

    /**
     * Telt het totaal aantal verwijderbare runs (voor bevestigingspagina).
     */
    public function countTerminalRuns(int $userId): int

    /**
     * Interne helper: verwijdert stream files + DB records in transactie.
     * 1. Verzamel stream file paths via getStreamFilePath()
     * 2. Verwijder stream files (file_exists() check, @unlink() voor graceful skip)
     * 3. DB transactie: delete runs via $run->delete()
     * Bij DB failure: rollback + Yii::error() log
     *
     * @param ClaudeRun[] $runs
     * @return int aantal verwijderde records
     */
    private function deleteRunsWithCleanup(array $runs): int
}
```

> **Note:** Service is een plain class (geen `extends Component`), consistent met `ClaudeStreamRelayService` en andere ClaudeController-services die via DI autowiring worden geïnjecteerd.

**Controller flow voor `actionDeleteSession(int $id)`:**
```php
// 1. Lookup met ownership scope — 404 als niet gevonden of niet van user
$run = ClaudeRun::find()
    ->forUser(Yii::$app->user->id)
    ->andWhere(['id' => $id])
    ->one() ?? throw new NotFoundHttpException();

// 2. Delegeer naar service
$deleted = $this->cleanupService->deleteSession($run);

// 3. Flash + redirect
Yii::$app->session->setFlash('success', "$deleted run(s) deleted.");
return $this->redirect(['runs']);
```

**Controller flow voor `actionCleanup()`:**
```php
$userId = Yii::$app->user->id;

// GET: toon bevestigingspagina
if (!Yii::$app->request->isPost) {
    return $this->render('cleanup-confirm', [
        'sessionCount' => $this->cleanupService->countTerminalSessions($userId),
        'runCount' => $this->cleanupService->countTerminalRuns($userId),
    ]);
}

// POST: voer bulk delete uit
$deleted = $this->cleanupService->bulkCleanup($userId);
Yii::$app->session->setFlash('success', "$deleted run(s) deleted.");
return $this->redirect(['runs']);
```

**Transactiestrategie:**
- Stream files worden EERST verwijderd (idempotent, geen rollback nodig)
- DB deletes in transactie — als een delete faalt, rollback alle DB wijzigingen
- Orphaned stream files (file verwijderd maar DB delete faalt) zijn acceptabel: ze zijn ephemeral data
- Error handling: bij DB failure wordt `Yii::error()` gelogd, exception doorgegooid naar controller

**Service `deleteSession` query logica:**
```php
// Als representativeRun een session_id heeft: alle terminal runs in die sessie
if ($representativeRun->session_id !== null) {
    $runs = ClaudeRun::find()
        ->forUser($representativeRun->user_id)
        ->forSession($representativeRun->session_id)
        ->terminal()
        ->all();
} else {
    // Standalone run: alleen deze run (mits terminal)
    $runs = $representativeRun->isTerminal() ? [$representativeRun] : [];
}
return $this->deleteRunsWithCleanup($runs);
```

**Validatie:**
- `forUser()` scope op alle queries — eigendom op DB-niveau, niet client-side
- `terminal()` scope op alle delete queries — nooit actieve runs verwijderen
- Ownership via query scope (consistent met bestaande `runs`, `stream-run`, `cancel-run` endpoints)
- `id` parameter is integer-cast in controller (`int $id`) — geen injection risico
- Stream file paths zijn server-generated via `getStreamFilePath()` — geen path traversal mogelijk
- VerbFilter: `delete-session` is POST-only (CSRF-bescherming via Yii2)
- `cleanup` actie: GET voor bevestigingspagina, POST met `confirm=1` voor uitvoering (CSRF via form token)

### Frontend

**Geen aparte JavaScript modules nodig.** De feature gebruikt:
- Yii2 built-in `data-confirm` voor JavaScript confirm dialoog
- Yii2 built-in `data-method="post"` voor POST requests via links
- Standaard form submission voor bulk cleanup bevestiging

**GridView delete-kolom implementatie:**
```php
[
    'label' => '',           // Lege header — actie-kolom
    'format' => 'raw',
    'contentOptions' => ['class' => 'text-center', 'style' => 'width: 50px;'],
    'value' => function (ClaudeRun $model): string {
        // Alleen tonen voor terminal sessies
        if (!in_array($model->getSessionLatestStatus(), ClaudeRunStatus::terminalValues(), true)) return '';
        return Html::a(
            '<i class="bi bi-trash"></i>',
            ['delete-session', 'id' => $model->id],
            [
                'class' => 'btn btn-sm btn-outline-danger',
                'title' => 'Delete session',
                'aria-label' => 'Delete session',
                'onclick' => 'event.stopPropagation();',
                'data' => [
                    'confirm' => 'Delete this session? (' . $model->getSessionRunCount() . ' runs will be removed)',
                    'method' => 'post',
                ],
            ]
        );
    },
]
```

## Test scenarios

### Unit tests

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| `testDeleteSessionRemovesAllRunsInSession` | Run met session_id die 3 runs bevat | Alle 3 runs verwijderd, return 3 |
| `testDeleteSessionRemovesSingleStandaloneRun` | Run zonder session_id | 1 run verwijderd, return 1 |
| `testDeleteSessionCleansUpStreamFiles` | Run met bestaand .ndjson bestand | Stream file wordt verwijderd |
| `testDeleteSessionSkipsMissingStreamFiles` | Run zonder .ndjson bestand | Geen error, return 1 |
| `testBulkCleanupDeletesOnlyTerminalRuns` | Mix van active + terminal runs | Alleen terminal runs verwijderd |
| `testBulkCleanupReturnsDeletedCount` | 5 terminal runs | Return 5 |
| `testBulkCleanupWithNoTerminalRuns` | Alleen actieve runs | Return 0, geen runs verwijderd |
| `testCountTerminalSessionsReturnsCorrectCount` | 3 terminal sessies, 1 actieve | Return 3 |
| `testDeleteSessionDoesNotDeleteActiveRuns` | Sessie met active + terminal runs | Alleen terminal runs verwijderd |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| `testDeleteSessionOfAnotherUserFindsNothing` | userId mismatch | Query returns 0, geen runs verwijderd |
| `testBulkCleanupOnlyAffectsCurrentUser` | Meerdere users met terminal runs | Alleen runs van opgegeven userId verwijderd |
| `testDeleteActiveRunIsRejected` | Run met status=running | Run wordt NIET verwijderd |
| `testCountTerminalRunsReturnsCorrectCount` | 5 terminal runs over 3 sessies | Return 5 |
| `testDeleteSessionWithMixedStatusesInSession` | Sessie met 2 completed + 1 running | 2 verwijderd, running run blijft bestaan |

### Test fixtures

De tests vereisen een fixture met:
- **User A**: eigenaar van test-runs
- **User B**: andere gebruiker (voor ownership tests)
- **Project**: minimaal 1 project van User A
- **Runs**:
  - 3 completed runs met zelfde `session_id` (sessie 1)
  - 1 failed standalone run (geen `session_id`)
  - 1 running run met zelfde `session_id` als een completed run (mixed sessie)
  - 1 pending run (standalone)
  - 1 completed run van User B (ownership test)

### Controller tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| `testDeleteSessionRejectsGetRequest` | GET naar `/claude/delete-session?id=1` | 405 Method Not Allowed (VerbFilter) |
| `testDeleteSessionReturns404ForNonexistentRun` | POST met onbekend id | 404 Not Found |
| `testCleanupShowsConfirmationPageOnGet` | GET naar `/claude/cleanup` | 200, render cleanup-confirm view |
| `testCleanupExecutesOnPost` | POST naar `/claude/cleanup` | Redirect naar runs met flash message |

### Regressie-impact

| Bestaande test | Impact |
|----------------|--------|
| `ClaudeRunQueryTest` | Geen wijziging — bestaande query scopes worden hergebruikt |
| `ClaudeRunSearchTest` | Geen wijziging — search model ongewijzigd |
| `ClaudeRunTest` | Geen wijziging — model ongewijzigd |
| `ClaudeControllerTest` | Mogelijk aanpassing als behaviors() getest wordt (nieuwe actions in VerbFilter + access control) |

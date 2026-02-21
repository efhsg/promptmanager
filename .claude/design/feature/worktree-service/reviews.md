# Spec Reviews — Worktree Service

## Review: Architect — 2026-02-21

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- **Plaatsing**: `yii/services/worktree/` subdirectory past bij bestaand patroon (`promptgeneration/`, `sync/`, `projectload/`)
- **DI**: Constructor injection voor `WorktreeService(PathService)` en `WorktreeController(WorktreeService, EntityPermissionService)` — correct, geen `Yii::$app` in services
- **Query class**: `forProject()`, `forUser()`, `withPurpose()` scopes volgen bestaand patroon
- **RBAC**: `matchCallback` met `EntityPermissionService::checkPermission()` + `findProject()` met directe `user_id` check — consistent met `AiChatController`
- **Error recovery**: Compensatie-logica bij `create()` (git remove bij DB failure) is goed doordacht
- **Eenvoud**: Geen overbodige abstracties; DTOs co-located bij service
- **Padafleiding**: `<root>-<suffix>` is deterministisch en simpel

### Verbeterd
- **`recreate()` git commando**: Was `git worktree add -b <branch> <path>` — `-b` faalt als branch al bestaat (en bij recreate bestaat de branch al, want worktree remove verwijdert alleen de directory, niet de branch). Gewijzigd naar `git worktree add <path> <branch>` (zonder `-b`).
- **View partial integratie**: Ontbrekende details toegevoegd over hoe `_worktrees.php` wordt geïncludeerd vanuit `project/view.php` — variabelen (`$model`), conditionele rendering, JS init call met config object en URL routes.

### Nog open
- Geen

---

## Review: Security — 2026-02-21

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- **RBAC per endpoint**: Twee groepen (project-level en worktree-level) met `matchCallback` — correct scheiding. Worktree-level operaties afleiden van project ownership via relatie
- **Shell injection preventie**: Alle `exec()` calls gebruiken `escapeshellarg()` — expliciet gedocumenteerd per commando met voorbeelden
- **Path traversal preventie**: Branch regex `^[a-zA-Z0-9/_.-]+$` + `rejectDoubleDots()` validator voorkomt `../../etc` aanvallen. Suffix regex `^[a-zA-Z0-9_-]+$` is nog restrictiever (geen `/` of `.`)
- **Error response sanitization**: Geen ruwe git output naar client — gesanitiseerde berichten, raw output alleen via `Yii::warning()`. Voorkomt server-path leaking
- **Output encoding**: Alle dynamische waarden in view partial via `Html::encode()` — expliciet per veld gedocumenteerd
- **CSRF**: Yii2 standaard actief voor POST, frontend stuurt `X-CSRF-Token` header. GET-only endpoint (`status`) hoeft geen CSRF
- **JSON body parsing**: Bevestigd dat `yii\web\JsonParser` geconfigureerd is in `config/web.php` — `fetchJson()` patroon werkt correct
- **Input validatie**: Alle 6 parameters met type, max-length, regex, en custom validators gedocumenteerd
- **VerbFilter**: GET/POST restricties expliciet per action

### Verbeterd
- Geen aanpassingen nodig — security-secties zijn volledig

### Aandachtspunten (niet-blokkerend)
- **Geen timeout op git operaties**: `exec()` calls hebben geen timeout. Bij zeer grote repos kan een merge of worktree add lang duren. Overweeg toekomstige `proc_open()` met timeout voor productie-hardening
- **Geen rate limiting**: Gebruiker kan vele worktrees aanmaken. Git file locking voorkomt race conditions, maar resource exhaustion is theoretisch mogelijk. Laag risico omdat alleen eigen projecten beïnvloed worden

### Nog open
- Geen

---

## Review: UX/UI Designer — 2026-02-21

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- **Smart defaults**: Purpose-selectie vult Branch en Suffix automatisch in — verlaagt cognitive load en inputfrictie
- **UI States**: Alle 12 states gedocumenteerd met visueel gedrag — inclusief subtiele states zoals "Syncing" (per-worktree spinner)
- **Post-actie flows**: Duidelijke tabel per actie met succes- en foutgedrag — toast voor succes, inline alert-danger voor fouten
- **Accessibility**: Uitgebreide specificatie — `aria-label`, `aria-live="polite"`, `aria-busy`, `role="alert"`, focus trapping, keyboard navigatie
- **Wireframe**: ASCII wireframe toont alle states en is consistent met component beschrijvingen
- **Copy-to-clipboard UX**: One-click copy met visuele feedback (`bi-clipboard` → `bi-check` voor 1s) — low-friction patroon
- **Contextual actions**: Missing-state toont Re-create/Cleanup i.p.v. Sync/Remove — tooltips leggen niet-voor-de-hand-liggende acties uit
- **Deep-linking**: `#worktrees` anchor + Prompt Instance view link — effectief cross-page navigatiepatroon
- **Usage hint**: "cd <path> && claude" onder de lijst — nuttige contextuele hulp
- **Responsive**: `d-flex flex-column flex-md-row` voor mobile → desktop layout

### Verbeterd
- **Remove bevestiging**: Was generieke "bevestigingsdialoog" — expliciet gemaakt als Bootstrap confirm-modal (niet `window.confirm()`) met `btn-secondary`/`btn-danger` knoppen. Consistent met de modal-gebaseerde UI van de app, afwijkend van het pagina-redirect patroon (bewust in AJAX-context)

### Nog open
- Geen

---

## Review: Front-end Developer — 2026-02-21

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- **IIFE patroon**: `window.WorktreeManager` volgt `ImportModal` patroon — consistent public API met `{ init, loadStatus }`
- **Fetch + JSON**: `fetchJson()` helper met `Content-Type: application/json`, CSRF header, XMLHttpRequest header — correct patroon, bevestigd door `JsonParser` configuratie
- **Hergebruik**: `QuillToolbar.copyToClipboard()` en `showToast()` correct geïdentificeerd en gebruikt
- **Conditional loading**: `registerJsFile()` i.p.v. `AppAsset` — JS alleen geladen op project view met root_directory
- **Smart defaults**: Purpose → Branch/Suffix auto-fill logica goed gedocumenteerd met "alleen invullen als velden nog leeg zijn" safeguard
- **Button loading states**: `setButtonLoading()` helper voor disable + spinner — consistent met ImportModal
- **CSRF handling**: `getCsrfToken()` via `<meta>` tag — correct patroon

### Verbeterd
- **Modal HTML locatie**: Was niet gespecificeerd waar modal HTML leeft. Toegevoegd: beide modals (create + confirm-remove) als statische HTML in `_worktrees.php`, met `id="createWorktreeModal"` en `id="confirmRemoveModal"`. Volgt `_import-modal.php` patroon met programmatische opening via `new bootstrap.Modal()`
- **Build tooling**: Was `terser` voor minificatie — verwijderd. Bestaande JS files in `AppAsset` zijn reguliere `.js` (niet `.min.js`). Alleen `cp` naar `yii/web/js/` is nodig, consistent met bestaand patroon
- **Asset registratie**: Was onduidelijk (`AppAsset` of `registerJsFile()`). Keuze gemaakt: `registerJsFile()` in partial voor conditioneel laden

### Nog open
- Geen

---

## Review: Developer — 2026-02-21

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- **Service-model scheiding**: `getFullPath()` op model (pure computed), `getContainerPath()` op service (PathService dependency) — correcte DI-grens
- **Error recovery**: `create()` compensatie-flow (git remove bij DB failure) is duidelijk en implementeerbaar. Geen transactie nodig bij single DB write — correct
- **Enum**: `WorktreePurpose` met `values()`, `label()`, `badgeClass()` — uitbreidbaar, volgt bestaand patroon
- **DTOs**: `WorktreeStatus` en `SyncResult` als readonly value objects — correcte scheiding data vs. logica
- **Migration**: `safeUp()`/`safeDown()` met `{{%table}}` syntax, FK met cascade, unique constraint — compleet
- **Model validatie**: `rules()` met regex, `rejectDoubleDots` custom validator, unique scope — goed gespecificeerd
- **Query class**: `forProject()`, `forUser()` (via project join), `withPurpose()` — standaard scopes, geen losse WHERE-clausules
- **Controller DI**: Constructor met `$id, $module, services, $config = []` + `parent::__construct()` — volgt `AiChatController`
- **Git behind count**: `rev-list --count HEAD..<source>` is correct voor lokale branch vergelijking
- **Geen overbodige abstractie**: Service + 2 DTOs + 1 enum = minimaal, direct implementeerbaar

### Verbeterd
- **`beforeAction()` voor JSON format**: Was "alle endpoints setten FORMAT_JSON" (per-action). Gewijzigd naar `beforeAction()` — alle 6 actions zijn JSON, dus éénmalige setting elimineert herhaling. Verschilt van `AiChatController` (gemengde action types) maar past bij pure AJAX controller

### Nog open
- Geen

---

## Review: Tester — 2026-02-21

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- **Test bestandslocaties**: 3 testbestanden duidelijk gespecificeerd — unit (service + model) en functioneel (controller)
- **Fixtures**: Gedifferentieerde fixtures met meerdere projecten (met/zonder root_dir, andere user) — goed voor isolatie
- **Service tests (nu 23)**: Dekken alle 10 service-methoden met happy path + foutpaden
- **Model tests (10)**: Validatie-regels uitputtend getest — regex, path traversal, enum, timestamps
- **Controller tests (nu 10)**: RBAC, input validatie, HTTP method restriction — goed op functioneel niveau
- **Edge case tests (9)**: Dekken filesystem-discrepanties, cascade delete, boundary conditions
- **Regressie-impact**: 4 bestaande componenten geïdentificeerd met risicobeoordeling en acties
- **Acceptatiecriteria**: Alle FR's hebben meetbare criteria met checkboxes

### Verbeterd
- **3 ontbrekende service tests toegevoegd**:
  - `testGetStatusForProjectReturnsEmptyWhenNoWorktrees` — lege array bij geen worktrees
  - `testSyncFailsWhenDirectoryMissing` — foutafhandeling bij sync zonder directory
  - `testCleanupRefusesWhenDirectoryExists` — voorkomt orphaned git worktrees bij actieve directory
- **1 ontbrekende controller test toegevoegd**:
  - `testSyncReturns403ForNonOwnerWorktree` — RBAC test voor worktree-level endpoints (niet alleen project-level)
- **Fixture verduidelijking**: Worktree fixtures nu met expliciet "missing" directory state voor cleanup/recreate tests, en expliciet "andere user" voor RBAC tests

### Nog open
- Geen

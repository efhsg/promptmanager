# Insights — Worktree Service

## Codebase onderzoek

### Vergelijkbare features
- **AiWorkspaceProviderInterface**: `yii/services/ai/AiWorkspaceProviderInterface.php` — lifecycle patroon (ensure/sync/delete/getPath) als inspiratie voor WorktreeService
- **ClaudeCliProvider git exec**: `yii/services/ai/providers/ClaudeCliProvider.php:692-710` — `getGitBranch()` gebruikt `exec()` + `escapeshellarg()` + exit code check
- **AiChatController RBAC**: `yii/controllers/AiChatController.php:69-121` — VerbFilter + AccessControl met matchCallback patroon

### Herbruikbare componenten
- **PathService::translatePath()**: `yii/services/PathService.php:156-165` — host→container padvertaling
- **QuillToolbar.copyToClipboard()**: `npm/src/js/editor-init.js:331-348` — clipboard met navigator.clipboard + execCommand fallback, exposed via `window.QuillToolbar`
- **QuillToolbar.showToast()**: `npm/src/js/editor-init.js:39-57` — Bootstrap toast via `window.QuillToolbar.showToast()`
- **ImportModal IIFE patroon**: `yii/views/layouts/_import-modal.php` — IIFE met `window.ImportModal = { open }` API
- **EntityPermissionService::checkPermission()**: `yii/services/EntityPermissionService.php` — RBAC check met model parameter

### Te volgen patterns
- **Controller DI**: `AiChatController` constructor met `$id, $module, ...services, $config = []` + `parent::__construct()`
- **TimestampTrait**: Gebruikt `date('Y-m-d H:i:s')` format (NIET unix timestamps) — spec migration klopt met DATETIME
- **LogCategory enum**: Huidige waarden: APPLICATION, DATABASE, AI, YOUTUBE, IDENTITY — WORKTREE past in dit patroon
- **Migration style**: `safeUp()/safeDown()`, `{{%table}}` syntax, class extends Migration
- **AppAsset JS registratie**: `public $js = [...]` array in `yii/assets/AppAsset.php`, of inline via `$this->registerJsFile()`

## Validatie spec tegen codebase

### Bevestigde patronen (spec klopt)
- ✅ `exec()` + `escapeshellarg()` patroon voor git commando's
- ✅ `PathService::translatePath()` voor padvertaling
- ✅ TimestampTrait met DATETIME format
- ✅ Controller DI via constructor
- ✅ VerbFilter + AccessControl behaviors patroon
- ✅ AJAX response format `['success' => bool, 'message' => string, 'data' => mixed]`
- ✅ IIFE module patroon voor JavaScript
- ✅ `window.QuillToolbar.copyToClipboard()` en `showToast()` beschikbaar
- ✅ CSRF via `<meta name="csrf-token">` en `X-CSRF-Token` header
- ✅ Modal HTML + JS patroon via layout partial

### Aandachtspunten
- Spec verwijst naar `copyToClipboard()` in `editor-init.js` — correct, exposed via `window.QuillToolbar`
- RBAC in spec gebruikt `EntityPermissionService::checkPermission('viewProject', $model)` — klopt met bestaand patroon
- Spec noemt `getCsrfToken()` in JS — bestaand patroon gebruikt `window.QuillToolbar.getCsrfToken()` of directe `<meta>` lookup

## Beslissingen
- `recreate()` gebruikt `git worktree add <path> <branch>` (zonder `-b`) — branch bestaat al na worktree remove
- Remove bevestiging via Bootstrap confirm-modal (niet `window.confirm()` of pagina-redirect) — passend in AJAX-context
- Modal HTML (create + confirm) in `_worktrees.php` partial — volgt `_import-modal.php` patroon
- Asset registratie via `registerJsFile()` (conditioneel) i.p.v. `AppAsset` (altijd)
- Geen JS minificatie — consistent met bestaande `.js` files in `AppAsset`
- View partial integratie via `$this->render('_worktrees', ['model' => $model])` conditioneel op `root_directory`

## Aanvullende beslissingen (uit reviews)
- `beforeAction()` voor JSON format in WorktreeController — alle 6 actions zijn JSON
- `cleanup()` verifiëert directory ontbreekt via `is_dir()` check — defense in depth
- Fixture beschrijving verduidelijkt met "missing" state en RBAC test data
- 4 extra tests toegevoegd: leeg project, sync missing dir, cleanup active dir, RBAC worktree-level

## Consistentiecheck resultaat
- ✅ Wireframe ↔ Componenten: alle UI elementen mappen
- ✅ Frontend ↔ Backend: alle 6 JS handlers ↔ 6 endpoints
- ✅ Edge cases ↔ Tests: elke edge case gedekt
- ✅ Architectuur ↔ Locaties: alle locaties consistent
- ✅ Security ↔ Endpoints: elk endpoint RBAC + validatie
- 1 verduidelijking: `cleanup()` directory check toegevoegd

## Open vragen
- Geen (spec is volledig)

## Blokkades
- Geen

## Eindresultaat
Alle 6 reviews >= 8/10. Consistentiecheck passed. Spec is implementatie-klaar.

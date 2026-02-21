# Feature: Worktree Management

## Samenvatting

Een generieke `WorktreeService` voor het beheren van meerdere git worktrees per project vanuit PromptManager. Ondersteunt parallel werken aan features, agent workspaces en community skills — elk in een geïsoleerde worktree.

## User story

Als gebruiker wil ik per project meerdere git worktrees kunnen aanmaken en beheren vanuit PromptManager, zodat ik (of meerdere agents) geïsoleerd kan werken aan features of andere taken — zonder elkaars werk of de main branch te verstoren.

## Functionele requirements

### FR-1: Worktree aanmaken

- Beschrijving: Gebruiker kan een git worktree aanmaken voor een project met een gekozen branch, pad-suffix en doel.
- Acceptatiecriteria:
  - [ ] Systeem voert `git -C <root> worktree add -b <branch> <root>-<suffix>` uit
  - [ ] Na aanmaken: `git -C <worktree> merge <source-branch> --no-edit` voor initiële sync
  - [ ] Record opgeslagen in `project_worktree` tabel met purpose, branch, suffix
  - [ ] UI toont bevestiging met worktree pad na succes
  - [ ] Foutmelding bij: geen git repo, pad bestaat al, git error — getoond als alert-danger IN de modal boven het formulier
  - [ ] Na succesvol aanmaken: modal sluiten, worktree lijst herladen, toast "Worktree created"

### FR-2: Worktree synchroniseren

- Beschrijving: Gebruiker kan een worktree synchroniseren met een source branch (standaard `main`).
- Acceptatiecriteria:
  - [ ] "Sync" knop per worktree
  - [ ] Systeem voert `git -C <worktree> merge <source-branch> --no-edit` uit
  - [ ] Bij succes: status badge update naar "In sync" inline + toast "Synced: X commits merged"
  - [ ] Bij merge conflict: UI toont foutmelding + instructies voor handmatige resolutie

### FR-3: Worktree status opvragen

- Beschrijving: Het systeem toont de live status van worktrees op de project view pagina.
- Acceptatiecriteria:
  - [ ] Sectie alleen zichtbaar als project een `root_directory` heeft en het een git repo is
  - [ ] Toont per worktree: pad (container), branch, sync status, doel
  - [ ] Sync status: aantal commits achter op source branch
  - [ ] Detecteert of worktree directory nog bestaat op filesystem
  - [ ] Loading state met spinner tijdens ophalen

### FR-4: Worktree verwijderen

- Beschrijving: Gebruiker kan een worktree verwijderen met bevestiging.
- Acceptatiecriteria:
  - [ ] "Remove" knop met Bootstrap confirm-modal (niet `window.confirm()`): "Remove worktree '&lt;suffix&gt;'? The worktree directory and local changes will be permanently deleted." met knoppen [Cancel] (`btn-secondary`) [Remove] (`btn-danger`). Modal sluit na Cancel; bij Remove → AJAX call → modal sluiten
  - [ ] Systeem voert `git -C <root> worktree remove <path> --force` uit
  - [ ] Record verwijderd uit `project_worktree` tabel
  - [ ] UI toont bevestiging na verwijdering
  - [ ] Graceful handling als directory al verwijderd is

### FR-5: Database tracking

- Beschrijving: Worktree metadata wordt bijgehouden in een `project_worktree` tabel.
- Acceptatiecriteria:
  - [ ] Migratie voor `project_worktree` tabel op zowel `yii` als `yii_test` schema
  - [ ] Velden: `id`, `project_id`, `purpose`, `branch`, `path_suffix`, `source_branch`, `created_at`, `updated_at`
  - [ ] `purpose` is een string-backed enum: `community-skills`, `feature`, `agent-workspace`
  - [ ] Unique constraint op `(project_id, path_suffix)`
  - [ ] Foreign key naar `project` met `ON DELETE CASCADE`
  - [ ] `ProjectWorktree` ActiveRecord model met relaties en query class

### FR-6: Filesystem-database reconciliatie

- Beschrijving: Het systeem detecteert discrepanties tussen database records en het filesystem.
- Acceptatiecriteria:
  - [ ] Als worktree in DB staat maar directory ontbreekt: markeer als "missing" in status
  - [ ] "Cleanup" optie om DB record op te schonen voor ontbrekende worktree
  - [ ] Worktrees die niet door PM zijn aangemaakt (via `git worktree list`) worden genegeerd

### FR-7: RBAC beveiliging

- Beschrijving: Alleen de project-eigenaar heeft toegang tot worktree-beheer.
- Acceptatiecriteria:
  - [ ] Alle AJAX endpoints valideren eigenaarschap via `EntityPermissionService::checkPermission('viewProject', ...)` in `behaviors()` matchCallback
  - [ ] Niet-eigenaar krijgt 403 Forbidden
  - [ ] Worktrees sectie niet zichtbaar voor niet-eigenaren

### FR-8: Workflow-integratie

- Beschrijving: Worktree-beheer is bereikbaar vanuit de plekken waar de gebruiker besluit een feature te implementeren.
- Acceptatiecriteria:
  - [ ] Worktree pad in lijst heeft een clipboard-copy knop (icoon `bi-clipboard`) naast het pad
  - [ ] Copy knop gebruikt bestaande `copyToClipboard()` uit `editor-init.js` (navigator.clipboard met execCommand fallback)
  - [ ] Na copy: icoon wisselt naar `bi-check` voor 1 seconde, daarna terug naar `bi-clipboard`
  - [ ] Project view worktrees sectie heeft `id="worktrees"` anchor voor deep-linking
  - [ ] Prompt Instance view pagina toont "Worktree" link-knop naast bestaande actieknoppen wanneer project een `root_directory` heeft
  - [ ] Worktree link navigeert naar `/project/view?id=X#worktrees` (deep-link naar worktrees sectie)
  - [ ] Link is `btn btn-outline-secondary` met icoon `bi-diagram-2`, consistent met bestaande knoppenrij
  - [ ] Link alleen zichtbaar als `$model->project->root_directory` is ingevuld

## Gebruikersflow

**Primaire flow (vanuit Project view):**

1. Gebruiker opent Project view pagina
2. Systeem checkt: heeft project `root_directory`? Is het een git repo?
3. **Geen root_directory**: Worktrees sectie niet gerenderd
4. **Geen git repo**: Waarschuwing "Root directory is geen git repository"
5. **Git repo**: Systeem laadt worktrees uit `project_worktree` tabel + live filesystem status
6. **Geen worktrees**: Lege lijst met "New Worktree" knop
7. Gebruiker klikt "New Worktree" → vult branch, suffix en doel in
8. Systeem maakt worktree aan, synchroniseert met source branch, slaat record op
9. **Worktrees bestaan**: Lijst met per worktree: pad, branch, status, acties (Sync/Remove)
10. Gebruiker klikt copy-icoon naast pad → pad gekopieerd naar clipboard
11. Gebruiker klikt "Sync" → worktree gesynchroniseerd
12. Gebruiker klikt "Remove" → bevestiging → worktree verwijderd

**Alternatieve flow (vanuit Prompt Instance view):**

1. Gebruiker genereert een prompt of bekijkt een bestaande prompt instance
2. Gebruiker ziet "Worktree" knop in actiebalk (alleen als project `root_directory` heeft)
3. Gebruiker klikt "Worktree" → navigeert naar Project view `#worktrees` sectie
4. Gebruiker maakt worktree aan of kopieert pad van bestaande worktree

## Edge cases

| Case | Gedrag |
|------|--------|
| Project zonder `root_directory` | Worktrees sectie verborgen |
| `root_directory` is geen git repo | Sectie toont melding; acties uitgeschakeld |
| Worktree pad niet bereikbaar in container | Foutmelding met PATH_MAPPINGS instructie |
| Worktree in DB maar directory ontbreekt | Status "missing"; cleanup + re-create optie |
| Worktree handmatig verwijderd buiten PM | Detectie via `is_dir()` check; toon cleanup optie |
| Zelfde suffix voor twee worktrees | Unique constraint op `(project_id, path_suffix)` voorkomt dit |
| Merge conflict bij sync | Foutmelding + CLI instructies voor handmatige resolutie |
| Zeer grote repo | Worktree aanmaken kan lang duren; spinner/feedback tonen |
| `root_directory` wijst naar worktree zelf | Detectie: vergelijk met bekende suffixes; toon waarschuwing |
| Gelijktijdige operaties | Buttons disablen tijdens AJAX; git regelt eigen locking |
| Project verwijderd (soft delete) | Worktrees blijven op filesystem; DB records via FK cascade verwijderd |
| Branch naam conflicteert met bestaande | `git worktree add -b` faalt; toon foutmelding met suggestie |
| Pad-suffix bevat ongeldige tekens | Validatie regex: `^[a-zA-Z0-9_-]+$` |

## Entiteiten en relaties

### Bestaande entiteiten
- **Project** (`yii/models/Project.php`) — `root_directory` is de basis voor worktree padafleiding
- **PathService** (`yii/services/PathService.php`) — `translatePath()` voor host→container vertaling
- **AiWorkspaceProviderInterface** (`yii/services/ai/AiWorkspaceProviderInterface.php`) — vergelijkbaar lifecycle patroon

### Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| `project_worktree` | Migratie | `yii/migrations/m260222_000000_create_project_worktree_table.php` | Nieuw: database tabel |
| `ProjectWorktree` | Model | `yii/models/ProjectWorktree.php` | Nieuw: ActiveRecord model |
| `ProjectWorktreeQuery` | Query | `yii/models/query/ProjectWorktreeQuery.php` | Nieuw: query class met scopes |
| `WorktreePurpose` | Enum | `yii/common/enums/WorktreePurpose.php` | Nieuw: purpose enum |
| `WorktreeService` | Service | `yii/services/worktree/WorktreeService.php` | Nieuw: generiek worktree CRUD + git operaties |
| `LogCategory::WORKTREE` | Enum waarde | `yii/common/enums/LogCategory.php` | Wijzigen: nieuwe case `WORKTREE = 'worktree'` toevoegen |
| `WorktreeStatus` | DTO | `yii/services/worktree/WorktreeStatus.php` | Nieuw: live worktree status |
| `SyncResult` | DTO | `yii/services/worktree/SyncResult.php` | Nieuw: sync resultaat |
| `WorktreeController` | Controller | `yii/controllers/WorktreeController.php` | Nieuw: AJAX endpoints |
| `_worktrees.php` | View partial | `yii/views/project/_worktrees.php` | Nieuw: worktrees sectie op project view |
| `view.php` | View | `yii/views/project/view.php` | Wijzigen: worktrees partial includen via `$this->render('_worktrees', ['model' => $model])`, conditioneel op `$model->root_directory` |
| `worktree-manager.js` | JavaScript | `npm/src/js/worktree-manager.js` | Nieuw: AJAX interacties + clipboard copy |
| `view.php` | View | `yii/views/prompt-instance/view.php` | Wijzigen: "Worktree" link-knop toevoegen aan actiebalk |

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| `PathService::translatePath()` | `yii/services/PathService.php` | Host→container padvertaling voor worktree pad |
| `exec()` + `escapeshellarg()` | `ClaudeCliProvider::getGitBranch()` | Patroon voor git commando's uitvoeren |
| `AiWorkspaceProviderInterface` | `yii/services/ai/AiWorkspaceProviderInterface.php` | Inspiratie voor lifecycle patroon (create/sync/delete) |
| `ProjectOwnerRule` | `yii/rbac/ProjectOwnerRule.php` | RBAC eigenaarschap voor alle endpoints |
| `EntityPermissionService` | `yii/services/EntityPermissionService.php` | Permission check in controller behaviors |
| `TimestampTrait` | `yii/models/traits/TimestampTrait.php` | `created_at`/`updated_at` voor ProjectWorktree |
| Bootstrap 5 card layout | `yii/views/project/view.php` | Card met header/body voor worktree sectie |
| AJAX response format | Alle controllers | `['success' => bool, 'message' => string, 'data' => mixed]` |
| Toast notifications | `npm/src/js/editor-init.js` | Feedback na acties via `showToast()` |
| `copyToClipboard()` | `npm/src/js/editor-init.js` | Clipboard copy met navigator.clipboard + execCommand fallback |
| AI button actiebalk | `yii/views/prompt-instance/view.php` | Patroon voor knoppenrij: `d-flex justify-content-end`, `btn me-2` |

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| `project_worktree` database tabel | N worktrees per project vereist metadata tracking. Filesystem-only schaalt niet: geen purpose/label, geen snelle queries, geen relatie met project. |
| `WorktreePurpose` enum | Typed purposes voorkomt vrije-tekst chaos. Extensible: nieuwe doelen als enum-waarde toevoegen. |
| Eén `WorktreeController` | Alle generieke worktree operaties (create/sync/status/remove/cleanup) in één controller. Specifieke toepassingen (community skills) krijgen eigen controller die dit fundament gebruikt. |
| `exec()` voor git commando's (niet `proc_open()`) | Git commando's zijn kort en synchroon. `exec()` past bij bestaand patroon in `ClaudeCliProvider::getGitBranch()`. |
| Padafleiding: `<root_directory>-<suffix>` | Deterministisch, sibling directory, valt onder zelfde mountpoint. Geen configuratie nodig. |
| `source_branch` per worktree | Elke worktree kan synchen met een andere branch (main, develop, feature-x). Standaard `main`. |
| View partial op Project view (niet eigen pagina) | Worktrees zijn context-gebonden aan een project. Aparte pagina voegt navigatie-overhead toe zonder meerwaarde. |
| DB als bron van waarheid, filesystem als live check | DB tracks intentie (welke worktrees zijn aangemaakt door PM), filesystem toont werkelijkheid. Discrepanties worden expliciet getoond. |
| Create via modal formulier | Branch, suffix en purpose invoeren vereist een formulier. Modal houdt de pagina schoon en volgt bestaand patroon (import modal). |
| `yii/services/worktree/` subdirectory | Service + 2 DTOs = 3 bestanden. Past bij bestaand patroon: `promptgeneration/` (3 bestanden), `sync/` (6+), `projectload/` (6+). Co-locatie van DTOs bij hun service. |
| Geen expliciete DI registratie | Yii2 container auto-resolves typed constructor parameters. `WorktreeService(__construct(PathService))` wordt automatisch opgelost. Alleen services met non-typed params (zoals `PathService` zelf) vereisen expliciete registratie. |
| Clipboard copy naast pad (niet selecteren + Ctrl+C) | One-click copy verlaagt frictie. Hergebruikt bestaand `copyToClipboard()` patroon uit `editor-init.js` met fallback. |
| Deep-link `#worktrees` anchor | Maakt cross-page navigatie mogelijk zonder extra routing. Prompt Instance view linkt direct naar worktrees sectie. Simpelste oplossing die werkt. |
| Worktree link op Prompt Instance view (niet modal herhalen) | Modal dupliceren op elke pagina is fragiel. Deep-link naar Project view houdt één bron van waarheid. Link alleen zichtbaar als project `root_directory` heeft — geen verwarring voor projecten zonder git repo. |

## Open vragen

- Geen

## UI/UX overwegingen

### Layout/Wireframe

```
┌──────────────────────────────────────────────────────────────────┐
│ Worktrees                                         [New Worktree] │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  [GEEN root_directory → sectie niet gerenderd]                  │
│                                                                  │
│  [GEEN GIT REPO]                                                │
│  ⚠ Root directory is not a git repository.                      │
│                                                                  │
│  [GEEN WORKTREES]                                               │
│  No worktrees configured. Click "New Worktree" to create one.   │
│                                                                  │
│  [WORKTREES BESTAAN — Bootstrap list-group]                     │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ community-skills           ● In sync     [Sync] [Remove]  │ │
│  │ Path: /projects/bes-lvs-skills  [📋]                       │ │
│  │ Branch: community-skills → main                            │ │
│  ├────────────────────────────────────────────────────────────┤ │
│  │ feature                    ● 3 behind     [Sync] [Remove]  │ │
│  │ Path: /projects/bes-lvs-auth  [📋]                         │ │
│  │ Branch: feature/auth → main                                │ │
│  ├────────────────────────────────────────────────────────────┤ │
│  │ agent-workspace            ✗ Missing  [Re-create] [Cleanup]  │ │
│  │                                       ↑ title="Re-create the git worktree from the saved configuration"
│  │                                       ↑ title="Remove database record (worktree directory no longer exists)"
│  │ Path: /projects/bes-lvs-agent1  [📋]                        │ │
│  │ Branch: agent/refactor → main                              │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  Usage: cd <path> && claude                                     │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘

┌─── New Worktree (modal) ──────────────────────────────────────┐
│                                                                │
│  Purpose:  (●) Community Skills  ( ) Feature  ( ) Agent       │
│  Branch:   [community-skills____]   ← auto-suggest bij keuze  │
│  Suffix:   [skills______________]   ← auto-suggest bij keuze  │
│  Source:   [main________________]   (sync target)              │
│                                                                │
│                               [Cancel]  [Create Worktree]      │
└────────────────────────────────────────────────────────────────┘

**Smart defaults:** Bij Purpose selectie worden Branch en Suffix automatisch ingevuld:
| Purpose | Branch suggestie | Suffix suggestie |
|---------|-----------------|------------------|
| Community Skills | `community-skills` | `skills` |
| Feature | `feature/` (cursor na slash) | `feature` |
| Agent Workspace | `agent/workspace` | `agent` |
Gebruiker kan suggesties overschrijven. Alleen invullen als velden nog leeg zijn.
```

### UI States

| State | Visueel |
|-------|---------|
| Loading | Spinner in card body tijdens status ophalen |
| No root_directory | Hele sectie verborgen (niet gerenderd) |
| No git repo | Card met waarschuwing |
| No worktrees | Lege state tekst + "New Worktree" knop |
| Worktree in sync | Groene badge "In sync" |
| Worktree behind | Oranje badge "X behind", Sync knop benadrukt |
| Worktree missing | Rode badge "Missing"; Re-create + Cleanup knoppen i.p.v. Sync/Remove |
| Creating | Modal submit knop disabled + spinner |
| Syncing | Sync knop disabled + spinner per worktree |
| Removing | Remove knop disabled + spinner, bevestiging al gehad |
| Error (lijst) | Alert-danger boven worktree lijst |
| Error (modal) | Alert-danger IN modal boven formulier; modal blijft open |
| Success | Toast notification rechtsonder |

### Post-actie flows

| Actie | Na succes | Na fout |
|-------|-----------|---------|
| Create | Modal sluiten → lijst herladen → toast "Worktree created" | Alert-danger IN modal boven formulier; modal blijft open |
| Sync | Badge update naar "In sync" inline → toast "Synced: X commits merged" | Alert-danger bij betreffende worktree item |
| Remove | Item verwijderen uit lijst (fade-out) → toast "Worktree removed" | Alert-danger bij betreffende worktree item |
| Re-create | Item status update naar "In sync" → toast "Worktree re-created" | Alert-danger bij betreffende worktree item |
| Cleanup | Item verwijderen uit lijst → toast "Record cleaned up" | Alert-danger bij betreffende worktree item |

### Accessibility

- Alle knoppen hebben `aria-label` met beschrijvende tekst
- Status badges hebben `aria-live="polite"` voor screenreaders
- Modal formulier: labels gekoppeld via `for`/`id`, focus trapped in modal
- Keyboard navigatie: Tab door worktree items en knoppen
- Spinner knoppen hebben `aria-busy="true"`
- Foutmeldingen hebben `role="alert"`

## Technische overwegingen

### Backend

#### Database: `project_worktree` tabel

Migratie gebruikt `safeUp()`/`safeDown()` voor transactie-veiligheid. Draait op zowel `yii` als `yii_test` schema.

```sql
CREATE TABLE {{%project_worktree}} (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    project_id    INT NOT NULL,
    purpose       VARCHAR(50) NOT NULL,        -- enum: community-skills, feature, agent-workspace
    branch        VARCHAR(255) NOT NULL,       -- git branch naam
    path_suffix   VARCHAR(100) NOT NULL,       -- suffix: <root>-<suffix>
    source_branch VARCHAR(255) NOT NULL DEFAULT 'main',  -- sync target
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL,
    FOREIGN KEY (project_id) REFERENCES {{%project}}(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_suffix (project_id, path_suffix)
);
```

#### ProjectWorktree model

```
ProjectWorktree extends ActiveRecord {
    use TimestampTrait;

    // Relaties
    getProject(): ActiveQuery  → belongsTo Project

    // Computed (pure — geen service dependencies)
    getFullPath(): string            // <project.root_directory>-<path_suffix>

    // Validatie
    rules(): array
        - project_id: required, integer, exist in project
        - purpose: required, in WorktreePurpose values
        - branch: required, string, max 255, regex `^[a-zA-Z0-9/_.-]+$`, custom: rejectDoubleDots
        - path_suffix: required, string, max 100, regex `^[a-zA-Z0-9_-]+$`, unique per project
        - source_branch: required, string, max 255, regex `^[a-zA-Z0-9/_.-]+$`, custom: rejectDoubleDots

    // Custom validator
    rejectDoubleDots(string $attribute): void
        if str_contains($this->$attribute, '..') → addError("mag geen '..' bevatten")

    // Query class
    static find(): ProjectWorktreeQuery
}
```

#### ProjectWorktreeQuery

```
ProjectWorktreeQuery extends ActiveQuery {
    forProject(int $projectId): self
    forUser(int $userId): self       // via project join
    withPurpose(WorktreePurpose $purpose): self
}
```

#### WorktreePurpose enum

```php
enum WorktreePurpose: string {
    case CommunitySkills = 'community-skills';
    case Feature = 'feature';
    case AgentWorkspace = 'agent-workspace';

    public static function values(): array
    {
        return array_map(static fn(self $p): string => $p->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::CommunitySkills => 'Community Skills',
            self::Feature => 'Feature',
            self::AgentWorkspace => 'Agent Workspace',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CommunitySkills => 'bg-info',
            self::Feature => 'bg-primary',
            self::AgentWorkspace => 'bg-warning text-dark',
        };
    }
}
```

#### WorktreeService — methoden

| Methode | Wat | Returns |
|---------|-----|---------|
| `create(Project $project, string $branch, string $suffix, WorktreePurpose $purpose, string $sourceBranch = 'main')` | Valideert, `git worktree add`, merge, DB record | `ProjectWorktree` |
| `sync(ProjectWorktree $worktree)` | `git -C <path> merge <source_branch> --no-edit` | `SyncResult` DTO |
| `getStatus(ProjectWorktree $worktree)` | Live filesystem + git check | `WorktreeStatus` DTO |
| `getStatusForProject(Project $project)` | Status van alle worktrees voor project | `WorktreeStatus[]` |
| `remove(ProjectWorktree $worktree)` | `git worktree remove` + DB delete | `bool` |
| `cleanup(ProjectWorktree $worktree)` | Verifieer dat directory ontbreekt (`is_dir()` check), dan DB delete. Exception als directory nog bestaat (voorkomt orphaned git worktree) | `bool` |
| `recreate(ProjectWorktree $worktree)` | `git worktree add <path> <branch>` (zonder `-b`, branch bestaat al) + merge `source_branch`. Gebruikt branch/suffix/source uit bestaand record. Update `updated_at`. | `ProjectWorktree` |
| `isGitRepo(Project $project)` | `git -C <path> rev-parse --git-dir` | `bool` |
| `getWorktreePath(Project $project, string $suffix)` | `<root>-<suffix>`, vertaald via PathService | `?string` |
| `getContainerPath(ProjectWorktree $worktree)` | Vertaalt `worktree->getFullPath()` via `PathService::translatePath()` | `string` |

Dependencies: `PathService` (DI via constructor)

#### Error recovery bij `create()`

```
1. Validate model (branch, suffix, purpose)
2. git worktree add -b <branch> <path>
3.   Als git faalt → throw exception (geen cleanup nodig)
4. git merge <source_branch> --no-edit
5.   Als merge faalt → worktree bestaat maar niet synced; NIET verwijderen, return met warning
6. Save ProjectWorktree record
7.   Als DB save faalt → git worktree remove (compensatie) → throw exception
```
Geen database transactie nodig — er is slechts één DB write. Git is inherent niet-transactioneel; compensatie via remove bij DB failure.

#### DTOs

```php
WorktreeStatus {
    int $worktreeId                // project_worktree.id
    bool $directoryExists          // is_dir() check
    string $containerPath          // pad in container
    string $hostPath               // pad op host
    string $branch                 // git branch naam
    string $sourceBranch           // sync target
    WorktreePurpose $purpose
    int $behindSourceCount         // commits achter op source branch
}

SyncResult {
    bool $success
    int $commitsMerged
    ?string $errorMessage          // bij merge conflict
}
```

#### WorktreeController endpoints

| Action | Method | Route | Beschrijving |
|--------|--------|-------|-------------|
| `actionStatus` | GET (AJAX) | `worktree/status?projectId=X` | Alle worktrees + status voor project |
| `actionCreate` | POST (AJAX) | `worktree/create` | Worktree aanmaken (body: `projectId`, `branch`, `suffix`, `purpose`, `sourceBranch`) |
| `actionSync` | POST (AJAX) | `worktree/sync` | Synchroniseren (body: `worktreeId`) |
| `actionRemove` | POST (AJAX) | `worktree/remove` | Verwijderen (body: `worktreeId`) |
| `actionRecreate` | POST (AJAX) | `worktree/recreate` | Worktree opnieuw aanmaken voor bestaand DB record (body: `worktreeId`) |
| `actionCleanup` | POST (AJAX) | `worktree/cleanup` | DB record opschonen (body: `worktreeId`) |

**Controller dependencies** (DI via constructor):
- `WorktreeService` — worktree CRUD + git operaties
- `EntityPermissionService` — RBAC permission checking

**VerbFilter + CSRF** via `behaviors()`:
```php
'verbs' => [
    'class' => VerbFilter::class,
    'actions' => [
        'status' => ['GET'],
        'create' => ['POST'],
        'sync' => ['POST'],
        'remove' => ['POST'],
        'recreate' => ['POST'],
        'cleanup' => ['POST'],
    ],
],
```
CSRF-validatie is standaard actief in Yii2 voor POST requests. Frontend stuurt CSRF token via `X-CSRF-Token` header (uit `<meta name="csrf-token">`).

**RBAC via `behaviors()`** (volgt `AiChatController` patroon):
```php
'access' => [
    'class' => AccessControl::class,
    'rules' => [
        // Project-level: projectId direct meegegeven
        [
            'actions' => ['status', 'create'],
            'allow' => true,
            'roles' => ['@'],
            'matchCallback' => function () {
                $projectId = (int) (Yii::$app->request->get('projectId')
                    ?: Yii::$app->request->post('projectId'));
                return $this->permissionService->checkPermission(
                    'viewProject', $this->findProject($projectId)
                );
            },
        ],
        // Worktree-level: projectId afgeleid via worktree record
        [
            'actions' => ['sync', 'remove', 'recreate', 'cleanup'],
            'allow' => true,
            'roles' => ['@'],
            'matchCallback' => function () {
                $worktree = $this->findWorktree(
                    (int) Yii::$app->request->post('worktreeId')
                );
                return $this->permissionService->checkPermission(
                    'viewProject', $worktree->project
                );
            },
        ],
    ],
],
```

**Private helpers:**
- `findProject(int $id): Project` — zoek project met user_id check (404 bij niet gevonden)
- `findWorktree(int $id): ProjectWorktree` — zoek worktree via join met project.user_id (404 bij niet gevonden)

**`beforeAction()` — JSON response format:**
```php
public function beforeAction($action): bool
{
    if (!parent::beforeAction($action)) {
        return false;
    }
    Yii::$app->response->format = Response::FORMAT_JSON;
    return true;
}
```
Alle 6 actions zijn AJAX/JSON — format éénmalig in `beforeAction()` i.p.v. per-action herhaling.

Alle endpoints retourneren `['success' => bool, 'message' => string, 'data' => mixed]`.

#### Input validatie

| Parameter | Validatie |
|-----------|----------|
| `projectId` | int, verplicht, eigenaar via RBAC |
| `worktreeId` | int, verplicht, eigenaar via project relatie |
| `branch` | string, verplicht, max 255, regex: `^[a-zA-Z0-9/_.-]+$`, extra validatie: mag geen `..` bevatten (path traversal preventie) |
| `suffix` | string, verplicht, max 100, regex: `^[a-zA-Z0-9_-]+$` |
| `purpose` | string, verplicht, in `WorktreePurpose` waarden |
| `sourceBranch` | string, optioneel (default `main`), max 255, regex: `^[a-zA-Z0-9/_.-]+$`, extra validatie: mag geen `..` bevatten |

#### Shell commando's — security en error handling

Alle shell commando's gebruiken `escapeshellarg()` en controleren de return code:

```php
// Patroon voor alle git exec() calls in WorktreeService:
exec($command . ' 2>&1', $output, $returnCode);
if ($returnCode !== 0) {
    $errorMessage = implode("\n", $output);
    Yii::warning("Git command failed: {$command} — {$errorMessage}", LogCategory::WORKTREE->value);
    // Throw of return error result afhankelijk van context
}
```

Commando's:
- `exec('git -C ' . escapeshellarg($rootPath) . ' worktree add -b ' . escapeshellarg($branch) . ' ' . escapeshellarg($worktreePath) . ' 2>&1', $output, $returnCode)`
- `exec('git -C ' . escapeshellarg($worktreePath) . ' merge ' . escapeshellarg($sourceBranch) . ' --no-edit 2>&1', $output, $returnCode)`
- `exec('git -C ' . escapeshellarg($rootPath) . ' worktree remove ' . escapeshellarg($worktreePath) . ' --force 2>&1', $output, $returnCode)`
- `exec('git -C ' . escapeshellarg($worktreePath) . ' rev-list --count HEAD..' . escapeshellarg($sourceBranch) . ' 2>&1', $output, $returnCode)` (behind count)

Commando's draaien in container context, paden vertaald via PathService.

#### Error response sanitization

Git foutmeldingen bevatten serverpaden. **Nooit** ruwe git output teruggeven aan de client:
- Bij create-fout: `"Failed to create worktree. Branch or path may already exist."`
- Bij sync-fout (merge conflict): `"Merge conflict detected. Resolve manually: cd <containerPath> && git merge --abort"`
- Bij remove-fout: `"Failed to remove worktree. Check if it is locked."`
- Ruwe git output alleen loggen via `Yii::warning()`, niet in JSON response

#### View partial output encoding

Alle dynamische waarden in `_worktrees.php` gebruiken `Html::encode()`:
- Branch namen: `Html::encode($status->branch)`
- Pad weergave: `Html::encode($status->containerPath)`
- Purpose labels: `Html::encode($status->purpose->value)`
- Foutmeldingen: `Html::encode($message)`

#### DI registratie

Geen expliciete registratie nodig. Yii2 container auto-resolves typed constructor parameters:
```php
// WorktreeService constructor — auto-resolved door DI container
public function __construct(private readonly PathService $pathService) {}
```

#### View partial integratie — project/view.php

In `yii/views/project/view.php`, na de bestaande detail card:

```php
<?php if ($model->root_directory): ?>
    <?= $this->render('_worktrees', ['model' => $model]) ?>
<?php endif; ?>
```

De partial `_worktrees.php` rendert:
1. Container div met `id="worktrees"` (anchor voor deep-linking)
2. Card skeleton met spinner (loading state)
3. Inline JS die `WorktreeManager.init()` aanroept met config:

```php
$this->registerJsFile('@web/js/worktree-manager.js', ['depends' => [AppAsset::class]]);
$this->registerJs("WorktreeManager.init(" . Json::encode([
    'container' => '#worktrees-list',
    'projectId' => $model->id,
    'urls' => [
        'status' => Url::to(['/worktree/status']),
        'create' => Url::to(['/worktree/create']),
        'sync' => Url::to(['/worktree/sync']),
        'remove' => Url::to(['/worktree/remove']),
        'recreate' => Url::to(['/worktree/recreate']),
        'cleanup' => Url::to(['/worktree/cleanup']),
    ],
]) . ");");
```

#### Prompt Instance view wijziging

Toevoegen aan actiebalk in `yii/views/prompt-instance/view.php`, naast bestaande AI/Update/Delete knoppen:

```php
<?php if ($model->project && $model->project->root_directory): ?>
    <?= Html::a(
        '<i class="bi bi-diagram-2"></i> Worktree',
        ['/project/view', 'id' => $model->project_id, '#' => 'worktrees'],
        ['class' => 'btn btn-outline-secondary me-2', 'title' => 'Create or manage worktrees for this project']
    ) ?>
<?php endif; ?>
```

Volgorde in actiebalk: **[AI] [Worktree] [Update] [Delete]**

### Frontend

#### JavaScript module: `worktree-manager.js`

**Patroon**: IIFE met public API op `window` (volgt `ImportModal` patroon):

```javascript
window.WorktreeManager = (function() {
    let config = {};  // container, projectId, urls

    const init = (cfg) => { ... };        // bewaar config, laad status
    const loadStatus = () => { ... };     // GET /worktree/status?projectId=X
    const renderList = (statuses) => { ... };
    const renderEmpty = () => { ... };
    const renderCard = (status) => { ... };
    const openCreateModal = () => { ... };
    const handleCreate = (formData) => { ... };  // POST /worktree/create
    const handleSync = (id) => { ... };          // POST /worktree/sync
    const handleRemove = (id) => { ... };        // POST /worktree/remove + confirm()
    const handleRecreate = (id) => { ... };      // POST /worktree/recreate
    const handleCleanup = (id) => { ... };       // POST /worktree/cleanup
    const copyPath = async (path, btn) => {      // clipboard copy met visuele feedback
        const originalHtml = btn.innerHTML;
        try {
            // Hergebruik QuillToolbar.copyToClipboard als beschikbaar, anders eigen fallback
            if (window.QuillToolbar && window.QuillToolbar.copyToClipboard) {
                await window.QuillToolbar.copyToClipboard(path);
            } else {
                await navigator.clipboard.writeText(path);
            }
            btn.innerHTML = '<i class="bi bi-check"></i>';
            setTimeout(() => { btn.innerHTML = originalHtml; }, 1000);
        } catch (e) {
            showError('Failed to copy path');
        }
    };

    // Helpers (private)
    const setButtonLoading = (btn, loading) => { ... };
    const showError = (msg) => { ... };          // alert-danger in container
    const showSuccess = (msg) => { ... };        // toast via QuillToolbar.showToast()
    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.content || meta.getAttribute('content')) : '';
    };
    const fetchJson = (url, data) => {           // POST met CSRF + AJAX headers
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        }).then(r => r.json());
    };

    return { init, loadStatus };  // public API
})();
```

#### Modal HTML

Beide modals (create + confirm-remove) staan als statische HTML in `_worktrees.php` (volgt `_import-modal.php` patroon):

- **Create modal**: `id="createWorktreeModal"` — form met Purpose radio group (`btn-group`), Branch/Suffix/Source text inputs (`form-control`), error alert container bovenaan body
- **Confirm-remove modal**: `id="confirmRemoveModal"` — body met waarschuwingstekst (dynamisch gevuld), footer met Cancel (`btn-secondary`) en Remove (`btn-danger`)

JS opent modals via `new bootstrap.Modal()` (programmatisch, niet via `data-bs-toggle`).

#### Asset registratie

Bestand: `npm/src/js/worktree-manager.js` → gekopieerd naar `yii/web/js/worktree-manager.js`

Registratie via `registerJsFile()` in `_worktrees.php` partial (conditioneel laden, alleen op project view):
```php
$this->registerJsFile('@web/js/worktree-manager.js', ['depends' => [AppAsset::class]]);
```

Build: voeg `build-worktree` script toe aan `npm/package.json`:
```json
"build-worktree": "cp src/js/worktree-manager.js ../yii/web/js/"
```
Geen minificatie nodig — consistent met bestaande JS files in `AppAsset` (reguliere `.js`, niet `.min.js`).

#### Responsive gedrag

- Worktree kaarten gebruiken Bootstrap responsive utilities
- Op **mobile** (< 768px): status badge + knoppen stacked onder pad/branch info
- `d-flex flex-column flex-md-row` voor kaart layout
- Knoppen: `btn-sm` op mobile, standaard op desktop
- Modal: `modal-dialog-scrollable` voor kleine schermen

## Test scenarios

### Test bestandslocaties

| Testbestand | Locatie |
|------------|---------|
| `WorktreeServiceTest` | `yii/tests/unit/services/worktree/WorktreeServiceTest.php` |
| `ProjectWorktreeTest` | `yii/tests/unit/models/ProjectWorktreeTest.php` |
| `WorktreeControllerTest` | `yii/tests/functional/controllers/WorktreeControllerTest.php` |

### Benodigde fixtures

| Fixture | Data |
|---------|------|
| `ProjectFixture` | Project met `root_directory` (git repo), Project zonder `root_directory`, Project van andere user |
| `ProjectWorktreeFixture` | 2 worktrees voor project 1 (purpose: feature + community-skills; één met bestaande dir, één simulerend "missing"), 1 worktree voor project 2 (andere user, voor RBAC tests) |

### Unit tests — WorktreeService

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| `testGetWorktreePathReturnsNullWithoutRootDir` | Project zonder root_directory | `null` |
| `testGetWorktreePathReturnsSuffixedPath` | Project `/projects/bes-lvs`, suffix `skills` | `/projects/bes-lvs-skills` |
| `testGetWorktreePathTranslatesHostPath` | Host path, suffix `skills` | Vertaald container pad + `-skills` |
| `testGetContainerPathTranslatesViaPathService` | Worktree met host path | PathService.translatePath() aangeroepen |
| `testIsGitRepoReturnsFalseForNonRepo` | Directory zonder `.git` | `false` |
| `testIsGitRepoReturnsTrueForRepo` | Directory met `.git` | `true` |
| `testCreateStoresDbRecord` | Valid project + branch + suffix + purpose | `ProjectWorktree` in DB |
| `testCreateRejectsDuplicateSuffix` | Zelfde project + suffix tweemaal | Exception |
| `testCreateRejectsNonGitRepo` | Project met non-git root_directory | Exception |
| `testCreateCleansUpWorktreeOnDbFailure` | Git add slaagt, DB save faalt (mock) | Git worktree remove aangeroepen als compensatie |
| `testSyncReturnsCommitCount` | Worktree achter op source | `SyncResult` met `commitsMerged > 0` |
| `testSyncReportsConflict` | Merge conflict | `SyncResult::$success === false`, `$errorMessage` gevuld |
| `testRemoveDeletesDbRecord` | Bestaande worktree | Record verwijderd |
| `testRemoveHandlesMissingDirectory` | DB record, geen dir | Record verwijderd, geen git error |
| `testRecreateCreatesWorktreeForExistingRecord` | DB record zonder dir | Git worktree add + merge, `updated_at` gewijzigd |
| `testRecreateFailsWhenDirectoryExists` | DB record met bestaande dir | Exception |
| `testCleanupRemovesOrphanedRecord` | DB record zonder dir | Record verwijderd |
| `testGetStatusDetectsMissingDirectory` | Record in DB, geen dir | `WorktreeStatus::$directoryExists === false` |
| `testGetStatusReturnsBehindCount` | Worktree 3 commits achter | `WorktreeStatus::$behindSourceCount === 3` |
| `testGetStatusForProjectReturnsAll` | Project met 2 worktrees | Array van 2 `WorktreeStatus` |
| `testGetStatusForProjectReturnsEmptyWhenNoWorktrees` | Project zonder worktrees | Lege array |
| `testSyncFailsWhenDirectoryMissing` | Worktree met ontbrekende directory | `SyncResult::$success === false`, foutmelding over ontbrekende directory |
| `testCleanupRefusesWhenDirectoryExists` | Worktree met bestaande directory | Exception — voorkomt orphaned git worktree |

### Unit tests — ProjectWorktree model

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| `testGetFullPathConcatenatesCorrectly` | root `/projects/app`, suffix `skills` | `/projects/app-skills` |
| `testGetFullPathReturnsNullWithoutRootDir` | Project zonder root_directory | `null` of exception |
| `testUniqueConstraintOnProjectSuffix` | Duplicate (project_id, path_suffix) | Validatie fout |
| `testPurposeAcceptsValidEnum` | `community-skills` | Valide |
| `testPurposeRejectsInvalidValue` | `invalid` | Validatie fout |
| `testBranchRejectsPathTraversal` | `../../etc` | Validatie fout (`rejectDoubleDots`) |
| `testSourceBranchRejectsPathTraversal` | `../hack` | Validatie fout (`rejectDoubleDots`) |
| `testSuffixRejectsSpecialChars` | `my worktree!` | Validatie fout |
| `testBranchAcceptsValidSlashes` | `feature/auth-flow` | Valide |
| `testTimestampsSetOnCreate` | Nieuw record | `created_at` en `updated_at` ingevuld |

### Controller tests (functioneel)

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| `testStatusReturnsWorktreeList` | GET status met valid projectId | JSON met worktree array |
| `testStatusReturns403ForNonOwner` | GET status met andermans project | HTTP 403 |
| `testCreateReturnsSuccess` | POST create met valide data | JSON `success: true`, record in DB |
| `testCreateReturns400ForInvalidBranch` | POST create met branch `../../hack` | JSON `success: false` |
| `testSyncReturnsResult` | POST sync met valid worktreeId | JSON met `commitsMerged` |
| `testRemoveDeletesRecordAndWorktree` | POST remove met valid worktreeId | JSON `success: true`, record verwijderd |
| `testRecreateReturnsSuccessForMissing` | POST recreate met worktreeId (dir ontbreekt) | JSON `success: true` |
| `testCleanupRemovesOrphanedRecord` | POST cleanup met worktreeId (dir ontbreekt) | JSON `success: true`, record verwijderd |
| `testPostEndpointsRejectGet` | GET op /create | HTTP 405 Method Not Allowed |
| `testSyncReturns403ForNonOwnerWorktree` | POST sync met worktreeId van andere user | HTTP 403 |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| `testCreateFailsForNonGitRepo` | root_directory is geen git repo | Exception met melding |
| `testCreateFailsWhenPathExists` | Sibling directory bestaat al | Exception met melding |
| `testSyncHandlesMergeConflict` | Conflict in worktree | `SyncResult::$success === false` |
| `testRemoveSucceedsWhenDirectoryExists` | Bestaande worktree | `true` |
| `testStatusHiddenWithoutRootDirectory` | Project zonder root_dir | Sectie niet gerenderd |
| `testCascadeDeleteRemovesRecords` | Project verwijderd | `project_worktree` records mee verwijderd |
| `testCreateRejectsBranchWithDoubleDots` | branch `../../hack` | Validatie fout |
| `testGetWorktreePathReturnsNullWhenPathNotReachable` | Worktree pad buiten PATH_MAPPINGS | `null` of foutmelding met instructie |
| `testDetectsRootDirectoryPointingToWorktree` | root_directory is zelf een bekende worktree path | Waarschuwing in status |

### Regressie-impact

| Bestaand onderdeel | Risico | Actie |
|--------------------|--------|-------|
| `yii/views/project/view.php` | Partial include kan bestaande layout breken | Verifieer dat project view nog correct rendert zonder worktrees |
| `ProjectController` | Geen wijzigingen aan controller logica | Laag risico, geen test-aanpassing nodig |
| `AppAsset` | JS toevoeging kan load order beïnvloeden | Verifieer dat bestaande JS nog werkt |
| `yii/views/prompt-instance/view.php` | Nieuwe knop in actiebalk kan layout verschuiven | Verifieer dat bestaande knoppen (AI/Update/Delete) nog correct renderen |

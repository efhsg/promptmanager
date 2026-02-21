# Analyse: Worktree Testing Gaps

## Samenvatting

De worktree-service feature heeft 42 unit tests (23 model + 19 service) die de kern goed afdekken.
Er zijn echter twee significante gaten: **controller/functionele tests ontbreken volledig**, en
meerdere service-scenario's uit de spec zijn nog niet getest.

---

## 1. Huidige testdekking

### Model tests (`ProjectWorktreeTest.php`) — 23 tests

| Categorie | Tests | Status |
|-----------|-------|--------|
| Path berekening | `getFullPath` (3 tests) | Volledig |
| Validatie — required fields | 1 test | Volledig |
| Validatie — purpose enum | 2 tests (valid + invalid) | Volledig |
| Validatie — branch security | 4 tests (path traversal, invalid chars via dataProvider) | Volledig |
| Validatie — source_branch | 1 test (path traversal) | Volledig |
| Validatie — suffix | 2 tests (special chars, slashes) | Volledig |
| Validatie — happy path | 3 tests (valid model, slashes in branch, dots in branch) | Volledig |
| Unique constraint | 1 test | Volledig |
| Default waarden | 1 test (source_branch defaults to main) | Volledig |
| Timestamps | 1 test | Volledig |
| Enum conversie | 1 test (`getPurposeEnum`) | Volledig |

**Conclusie:** Model tests zijn compleet. Geen gaten.

### Service tests (`WorktreeServiceTest.php`) — 19 tests

| Categorie | Tests | Status |
|-----------|-------|--------|
| Path berekening | 4 tests (null, suffixed, host-translate, container) | Volledig |
| Git repo detectie | 3 tests | Volledig |
| Create — foutpaden | 3 tests (non-git, no root_dir, invalid branch) | Volledig |
| Create — happy path | 1 test (DB record + directory check) | Volledig |
| Sync — foutpad | 1 test (missing directory) | Aanwezig |
| Sync — happy path | 1 test (up to date, 0 commits) | Beperkt |
| Remove — happy path | 1 test (DB + directory verwijderd) | Volledig |
| Status — missing dir | 1 test | Aanwezig |
| Status — project level | 1 test (empty when no worktrees) | Beperkt |
| Cleanup — foutpad | 1 test (refuses when dir exists) | Aanwezig |
| Cleanup — happy path | 1 test (orphaned record) | Volledig |
| Recreate — foutpad | 1 test (dir already exists) | Aanwezig |

**Conclusie:** Basis gedekt, maar mist scenario's met daadwerkelijke git-activiteit.

### Controller tests — 0 tests

**Volledig afwezig.** De spec vermeldt `WorktreeControllerCest` maar die is nooit aangemaakt.

---

## 2. Geidentificeerde gaten

### A. Controller tests (hoge prioriteit)

De `WorktreeController` heeft 6 AJAX endpoints met RBAC, verb filtering, en JSON responses.
Er zijn **nul** functionele tests. Dit is het grootste gat.

**Ontbrekende tests per spec:**

| Test | Wat het valideert |
|------|-------------------|
| `testStatusReturnsWorktreeList` | GET status met valid projectId → JSON met worktrees |
| `testStatusReturns403ForNonOwner` | GET status met andermans project → HTTP 403 |
| `testCreateReturnsSuccess` | POST create met valide data → `success: true` |
| `testCreateReturns400ForInvalidPurpose` | POST create met ongeldige purpose → `success: false` |
| `testSyncReturnsResult` | POST sync met valid worktreeId → JSON met `commitsMerged` |
| `testRemoveDeletesRecordAndWorktree` | POST remove → `success: true`, record weg |
| `testRecreateReturnsSuccess` | POST recreate (dir ontbreekt) → `success: true` |
| `testCleanupRemovesOrphanedRecord` | POST cleanup (dir ontbreekt) → `success: true` |
| `testPostEndpointsRejectGet` | GET op /create → HTTP 405 |
| `testSyncReturns403ForNonOwnerWorktree` | POST sync met andermans worktree → HTTP 403 |
| `testUnauthenticatedUserRedirected` | Geen login → redirect naar login |

**Uitdaging:** De controller endpoints voeren echte git-operaties uit via `WorktreeService`.
Functionele tests in Yii2/Codeception draaien met de echte applicatie (geen mocking van services).
Dit betekent dat tests die `create` of `sync` testen een echte git repo nodig hebben.

**Mogelijke aanpakken:**

1. **Alleen permission/routing tests** — Test RBAC, verb filtering, authentication zonder echte
   git operaties. Dit is het meest pragmatisch en dekt de controller-specifieke logica.

2. **Volledige integratie met temp repo** — Zet een temp git repo op in `_before()`, wijs een
   project fixture naar die repo, en draai de hele flow. Complex maar volledige dekking.

3. **Hybride** — Permission tests als Cest (functioneel), en create/sync integratie als Unit test
   met directe service + controller aanroep.

**Aanbeveling:** Aanpak 1 (permission/routing) als eerste stap. De service-laag is al unit-getest;
de controller voegt alleen RBAC, routing en JSON-formatting toe. Die controller-specifieke logica
is precies wat functionele tests moeten valideren.

### B. Service tests — ontbrekende scenario's (gemiddelde prioriteit)

| Ontbrekend scenario | Waarom relevant | Moeilijkheidsgraad |
|--------------------|-----------------|--------------------|
| Sync met commits achter (>0) | Huidige test test alleen "up to date" | Gemiddeld — vereist commits toevoegen aan main na worktree create |
| Sync met merge conflict | Cruciaal pad: conflict → abort → foutmelding | Gemiddeld — vereist conflicterende wijzigingen |
| Recreate happy path | Hele flow: prune → add → merge → update timestamp | Gemiddeld — vereist worktree remove + recreate |
| Remove met missing directory | Graceful handling als dir al weg is | Laag |
| Status met behind count >0 | Verifieert `getBehindCount` met echte commits | Gemiddeld |
| Status voor project met meerdere worktrees | `getStatusForProject` returns all | Laag |
| Create met duplicate suffix | Validatie voorkomt duplicates | Laag (model test dekt dit al) |

### C. Edge case tests (lage prioriteit)

| Ontbrekend scenario | Waarom relevant |
|--------------------|-----------------|
| Cascade delete verwijdert worktree records | FK ON DELETE CASCADE werkt correct |
| Create met branch die al bestaat | Git `-b` faalt, foutmelding correct |
| Sync op net-aangemaakte worktree (al in sync) | Idempotent gedrag |

---

## 3. Testinfrastructuur analyse

### Huidige opzet

- **Unit tests**: `Codeception\Test\Unit` met fixtures (ORM/fixtures module)
- **Functionele tests**: `Cest` patroon met `FunctionalTester` (Yii2 + Db modules)
- **Fixtures**: `UserFixture`, `ProjectFixture`, `ProjectWorktreeFixture` (3 records)
- **Temp git repos**: Service tests maken temp directories aan in `setUp()` en ruimen op in `tearDown()`

### Wat nodig is voor controller tests

De bestaande Cest tests (`PromptInstanceControllerCest`, etc.) gebruiken:
- `$I->haveFixtures([...])` voor data setup
- `$I->amLoggedInAs($userId)` voor authenticatie
- `$I->amOnRoute('/path')` voor GET requests
- `$I->seeResponseCodeIs(code)` voor status code checks
- `$I->seeInDatabase(table, data)` voor DB verificatie
- RBAC fixtures (`AuthRuleFixture`, `AuthItemFixture`, etc.)

**Voor WorktreeControllerCest extra nodig:**
- Manier om AJAX POST requests te simuleren met JSON body
- CSRF token handling (of disablen in test config)
- JSON response parsing (`$I->grabResponse()` + `json_decode`)
- Optioneel: temp git repo als we create/sync willen testen

### Fixture gap

De huidige `ProjectWorktreeFixture` heeft 3 records:
1. `worktree1`: project_id=1, feature/auth, suffix=auth (user 100)
2. `worktree2`: project_id=1, bugfix/login-error, suffix=login-fix (user 100)
3. `worktree3`: project_id=2, refactor/cleanup, suffix=cleanup (user 101 — andere user)

Dit is voldoende voor RBAC tests (eigen vs andermans worktree).

---

## 4. Prioritering

| Prioriteit | Wat | Impact | Effort |
|------------|-----|--------|--------|
| **P1** | Controller permission/RBAC tests (Cest) | Hoog — geen RBAC tests = ongevalideerde beveiliging | Gemiddeld (volgt bestaand Cest patroon) |
| **P2** | Controller routing/verb tests | Gemiddeld — valideert HTTP method enforcement | Laag |
| **P3** | Service tests: sync met echte commits | Gemiddeld — dekt belangrijk scenario | Gemiddeld |
| **P3** | Service tests: merge conflict handling | Gemiddeld — dekt foutpad | Gemiddeld |
| **P4** | Service tests: recreate happy path | Laag — foutpad al getest | Gemiddeld |
| **P4** | Service tests: remove met missing dir | Laag — simpel scenario | Laag |
| **P5** | Edge case: cascade delete | Laag — framework gedrag | Laag |

---

## 5. Aanbevolen implementatievolgorde

### Stap 1: `WorktreeControllerCest` — Permission & routing tests

Maak `yii/tests/functional/controllers/WorktreeControllerCest.php` aan met:

- Fixtures: User, Project, ProjectWorktree, Auth* (RBAC)
- Tests voor:
  - Unauthenticated user → redirect
  - Owner kan status opvragen → 200 + JSON
  - Non-owner wordt geblokkeerd → 403
  - POST endpoints weigeren GET → 405
  - Sync/remove/recreate/cleanup met andermans worktree → 403

**Complexiteit:** De controller retourneert altijd JSON. De `FunctionalTester` is primair
ontworpen voor HTML responses. Voor AJAX endpoints moeten we `sendAjaxPostRequest` of
`$I->haveHttpHeader('X-Requested-With', 'XMLHttpRequest')` gebruiken.

Alternatief: schrijf de controller tests als Unit tests die de controller direct aanroepen
via `Yii::$app->runAction()`, vergelijkbaar met hoe de service tests nu werken. Dit geeft
meer controle over headers en response parsing.

### Stap 2: Service tests — git-scenario's

Bouw voort op de bestaande `initGitRepo()` helper:

```php
// Sync met commits achter
private function addCommitToMain(string $repoPath): void
{
    exec('git -C ' . escapeshellarg($repoPath) . ' checkout main 2>&1');
    exec('git -C ' . escapeshellarg($repoPath) . ' commit --allow-empty -m "new commit" 2>&1');
    // Switch terug naar worktree branch niet nodig — worktree is separate directory
}

// Merge conflict
private function createConflict(string $repoPath, string $worktreePath): void
{
    // In main: wijzig bestand
    file_put_contents($repoPath . '/conflict.txt', 'main version');
    exec('git -C ' . escapeshellarg($repoPath) . ' add . && git -C ' . escapeshellarg($repoPath) . ' commit -m "main change" 2>&1');

    // In worktree: wijzig hetzelfde bestand anders
    file_put_contents($worktreePath . '/conflict.txt', 'worktree version');
    exec('git -C ' . escapeshellarg($worktreePath) . ' add . && git -C ' . escapeshellarg($worktreePath) . ' commit -m "worktree change" 2>&1');
}
```

### Stap 3: Overige scenario's

- Recreate happy path (create → remove dir handmatig → recreate)
- Remove met missing directory
- Status met behind count
- Cascade delete verificatie

---

## 6. Risico's en aandachtspunten

| Risico | Mitigatie |
|--------|----------|
| Functionele tests met git zijn traag | Beperk tot essentiële scenario's; permission tests hoeven geen git |
| Temp directories niet opgeruimd bij test failure | `tearDown()` al robuust (glob cleanup pattern) |
| CSRF token in functionele AJAX tests | Gebruik `$I->haveHttpHeader()` of disable CSRF in test config |
| Git versie verschilt per omgeving | Tests gebruiken basis git commando's (init, add, commit, merge) — universeel |
| Worktree fixtures wijzen naar non-existent paths | OK voor permission tests; service tests maken eigen temp dirs |

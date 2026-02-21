# Implementation Plan: Worktree Management

## Scope

18 files total (10 new, 4 modified, 4 test/fixture). Classification: **L** (multi-phase). 3 phases.

## Execution Rules

1. One phase per session
2. Commit after each phase — app must work after every commit
3. Run tests before committing
4. Read impl/todos.md first — every session
5. Only read the current phase section, not the full spec

## Phases

### P1: Data Foundation

All data layer components — migration, enums, DTOs, model, query class, and model unit tests.

**Files:**
1. `yii/migrations/m260222_000000_create_project_worktree_table.php` (new)
2. `yii/common/enums/WorktreePurpose.php` (new)
3. `yii/common/enums/LogCategory.php` (modify — add WORKTREE case)
4. `yii/services/worktree/SyncResult.php` (new)
5. `yii/services/worktree/WorktreeStatus.php` (new)
6. `yii/models/ProjectWorktree.php` (new)
7. `yii/models/query/ProjectWorktreeQuery.php` (new)
8. `yii/tests/unit/models/ProjectWorktreeTest.php` (new)

**Depends on:** none
**Validation:** Migration runs on both schemas. Model validates correctly. Unit tests pass.
**Commit message:** `ADD: project worktree data layer — migration, model, enums, DTOs`

### P2: Service + Controller

Business logic service with git operations, AJAX controller with RBAC, fixture, and service unit tests.

**Files:**
1. `yii/services/worktree/WorktreeService.php` (new)
2. `yii/controllers/WorktreeController.php` (new)
3. `yii/tests/fixtures/ProjectWorktreeFixture.php` (new)
4. `yii/tests/unit/services/worktree/WorktreeServiceTest.php` (new)

**Depends on:** P1
**Validation:** Service unit tests pass. Controller endpoints respond correctly.
**Commit message:** `ADD: worktree service and controller with RBAC`

### P3: Frontend

View partial, JavaScript module, view modifications, and build script.

**Files:**
1. `npm/src/js/worktree-manager.js` (new)
2. `yii/web/js/worktree-manager.js` (copy of above)
3. `yii/views/project/_worktrees.php` (new)
4. `yii/views/project/view.php` (modify — include worktrees partial)
5. `yii/views/prompt-instance/view.php` (modify — add Worktree link button)
6. `npm/package.json` (modify — add build-worktree script)

**Depends on:** P2
**Validation:** Project view renders worktrees section. Prompt instance view shows Worktree button. JS builds correctly.
**Commit message:** `ADD: worktree management UI — views, JavaScript, build`

## Dependency Graph

```
P1 (Data Foundation)
 └── P2 (Service + Controller)
      └── P3 (Frontend)
```

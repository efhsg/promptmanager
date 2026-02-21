# Code Review Context

## Change

Nieuwe feature: Worktree Management — een generieke WorktreeService voor het beheren van meerdere git worktrees per project vanuit PromptManager. Omvat complete CRUD (create/sync/status/remove/cleanup/recreate), database tracking, RBAC beveiliging, en een JavaScript-gedreven UI op de project view pagina.

## Scope

- `yii/common/enums/WorktreePurpose.php` — Nieuw: string-backed enum
- `yii/common/enums/LogCategory.php` — Gewijzigd: WORKTREE case
- `yii/migrations/m260222_000000_create_project_worktree_table.php` — Nieuw: migratie
- `yii/models/ProjectWorktree.php` — Nieuw: ActiveRecord model
- `yii/models/query/ProjectWorktreeQuery.php` — Nieuw: Query class
- `yii/services/worktree/WorktreeService.php` — Nieuw: service + git operaties
- `yii/services/worktree/WorktreeStatus.php` — Nieuw: DTO
- `yii/services/worktree/SyncResult.php` — Nieuw: DTO
- `yii/controllers/WorktreeController.php` — Nieuw: AJAX controller
- `yii/views/project/_worktrees.php` — Nieuw: view partial
- `yii/views/project/view.php` — Gewijzigd: partial render
- `yii/views/prompt-instance/view.php` — Gewijzigd: worktree link-knop
- `npm/src/js/worktree-manager.js` — Nieuw: JavaScript module
- `yii/web/js/worktree-manager.js` — Nieuw: gekopieerde JS
- `npm/package.json` — Gewijzigd: build-worktree script
- `yii/tests/fixtures/ProjectWorktreeFixture.php` — Nieuw: fixture
- `yii/tests/fixtures/data/project_worktrees.php` — Nieuw: fixture data
- `yii/tests/unit/models/ProjectWorktreeTest.php` — Nieuw: 16 model tests
- `yii/tests/unit/services/worktree/WorktreeServiceTest.php` — Nieuw: 13 service tests

## Type

Full-stack (backend PHP + service + migration + controller + views + frontend JS + tests)

## Reviewvolgorde

1. Reviewer
2. Architect
3. Security
4. Front-end Developer
5. Developer
6. Tester

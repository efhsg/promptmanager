# Implementation Plan: AI Provider Abstraction Layer

## Lessons Learned

A previous attempt to implement this spec in a single session caused:

1. **CLI crash** — Context window exhaustion from holding the full spec + all file reads + all edits
2. **Broken app** — Partial renames left the app in an inconsistent state (e.g., table renamed but model still referencing old name)

**Root cause:** The spec describes ~70 file changes across 7 functional requirements. Implementing all at once exceeds what a single Claude Code session can safely handle.

## Execution Rules

1. **One phase per session** — Each phase is a single Claude Code conversation
2. **Commit after each phase** — Creates restore points; if next phase fails, app still works
3. **Run tests after each phase** — `vendor/bin/codecept run unit` must pass before committing
4. **Run migrations on both schemas** — `./yii migrate` + `./yii_test migrate`
5. **Read `impl/todos.md` first** — Every new session starts by reading implementation state
6. **Max ~10 file changes per phase** — Keeps context manageable
7. **Never start a phase without completing the previous** — Dependencies are strict

## Phase Overview

| Phase | Description | Files | Commit? | App works? |
|-------|-------------|-------|---------|------------|
| P1 | Create interfaces | 5 new | Yes | Yes — no behavior change |
| P2 | Create ClaudeCliProvider (extract from services) | 3 new, 3 edit | Yes | Yes — old services still exist |
| P3 | Migration: rename tables + columns | 3 new migrations | Yes | Yes — after model updates in P4 |
| P4 | Rename models (AiRun, AiRunQuery, AiRunSearch, AiRunStatus, AiRunOwnerRule) | 5 rename, 5+ edit | Yes | Yes |
| P5 | Rename services + jobs + handlers | 4 rename, 3+ edit | Yes | Yes |
| P6 | Rename AiPermissionMode + update Project model | 1 rename, 1 edit | Yes | Yes |
| P7 | Rename controllers + routes + RBAC | 3 rename, 3 edit config | Yes | Yes |
| P8 | Update views + CSS + JS | 3 rename, 16 edit | Yes | Yes |
| P9 | DI wiring, config, bootstrap aliases | 3 edit config | Yes | Yes |
| P10 | Rename + update tests | 17 rename/edit | Yes | Yes |
| P11 | Cleanup: remove old files, verify | delete old, sweep | Yes | Yes |

---

## P1: Create Interfaces (no behavior change)

**Goal:** Define the abstraction layer contracts. No existing code changes.

**New files:**
1. `yii/services/ai/AiProviderInterface.php` — `execute()`, `cancelProcess()`, `getName()`, `getIdentifier()`
2. `yii/services/ai/AiStreamingProviderInterface.php` — `executeStreaming()`, `parseStreamResult()`
3. `yii/services/ai/AiWorkspaceProviderInterface.php` — `ensureWorkspace()`, `syncConfig()`, `deleteWorkspace()`, `getWorkspacePath()`, `getDefaultWorkspacePath()`
4. `yii/services/ai/AiUsageProviderInterface.php` — `getUsage()`
5. `yii/services/ai/AiConfigProviderInterface.php` — `hasConfig()`, `checkConfig()`, `loadCommands()`, `getSupportedPermissionModes()`

**Validation:** Interfaces exist, no errors on autoload. Existing tests still pass.

**Commit:** `ADD: define AI provider interfaces (abstraction layer)`

---

## P2: Create ClaudeCliProvider (extract + compose)

**Goal:** Create the concrete Claude provider that implements all interfaces, by extracting logic from `ClaudeCliService` + `ClaudeWorkspaceService`.

**New files:**
1. `yii/services/ai/providers/ClaudeCliProvider.php` — implements all 5 interfaces
   - Compose from: `ClaudeCliService` (execute, streaming, config, commands) + `ClaudeWorkspaceService` (workspace)
   - Move stream parsing (`extractResultText`, `extractMetadata`, `extractSessionId`) from `RunClaudeJob` to `parseStreamResult()`

**Edited files:**
2. `yii/services/ClaudeCliCompletionClient.php` — update internal dependency type hint (accept both old and new)
3. `yii/config/main.php` — register `AiProviderInterface::class => ClaudeCliProvider::class` in DI container

**Important:** Do NOT delete `ClaudeCliService` or `ClaudeWorkspaceService` yet. Both old and new coexist.

**Validation:** `Yii::$container->get(AiProviderInterface::class)` returns `ClaudeCliProvider`. Existing tests still pass.

**Commit:** `ADD: ClaudeCliProvider implementing all AI provider interfaces`

---

## P3: Database Migrations

**Goal:** Rename tables and columns. Add `provider` column.

**New files:**
1. `yii/migrations/m260217_000001_rename_claude_run_to_ai_run.php`
   - `RENAME TABLE {{%claude_run}} TO {{%ai_run}}`
   - `ALTER TABLE {{%ai_run}} ADD COLUMN provider VARCHAR(50) NOT NULL DEFAULT 'claude' AFTER project_id`
   - Rename foreign keys and indexes
2. `yii/migrations/m260217_000002_rename_claude_columns_in_project.php`
   - `claude_options` → `ai_options`
   - `claude_context` → `ai_context`
   - `claude_permission_mode` → `ai_permission_mode`
3. `yii/migrations/m260217_000003_rename_claude_rbac_to_ai.php`
   - Add `AiRunOwnerRule` to `auth_rule`
   - Rename permissions: `viewClaudeRun` → `viewAiRun`, `updateClaudeRun` → `updateAiRun`
   - Update rule references
   - Remove old `ClaudeRunOwnerRule`
   - `UPDATE {{%queue}} SET channel = 'ai' WHERE channel = 'claude'`

**Run on both schemas:**
```bash
cd /var/www/html/yii
./yii migrate --migrationNamespaces=app\\migrations --interactive=0
./yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
```

**Validation:** Migrations run forward and backward (`safeDown`). Tables renamed in both schemas.

**Commit:** `ADD: migrations to rename Claude tables/columns to AI-agnostic names`

**Note:** After this phase, the OLD model code still works because migrations just rename — but P4 must follow immediately.

---

## P4: Rename Models + Enums + RBAC Rule

**Goal:** Rename model layer to match new table names.

**Renamed files (git mv):**
1. `ClaudeRun.php` → `AiRun.php` — update `tableName()` to `{{%ai_run}}`, add `provider` attribute, update class references
2. `ClaudeRunQuery.php` → `AiRunQuery.php` — update namespace, class name
3. `ClaudeRunSearch.php` → `AiRunSearch.php` — update model references
4. `ClaudeRunStatus.php` → `AiRunStatus.php` — rename enum
5. `ClaudeRunOwnerRule.php` → `AiRunOwnerRule.php` — update class name, model reference

**Edited files (update references):**
6. `RunClaudeJob.php` — change `ClaudeRun` → `AiRun` references
7. `ClaudeController.php` — change model references
8. `ClaudeRunController.php` — change model references
9. `ClaudeRunCleanupService.php` — change model references
10. `ClaudeStreamRelayService.php` — change model references
11. Any other files referencing `ClaudeRun`, `ClaudeRunStatus`, `ClaudeRunQuery`

**Validation:** `vendor/bin/codecept run unit` — tests that reference old class names will fail; fix test references in P10 or update minimally here to keep passing.

**Commit:** `REFACTOR: rename ClaudeRun model layer to AiRun`

---

## P5: Rename Services + Job + Handler

**Goal:** Rename remaining Claude-specific services to AI-agnostic names.

**Renamed files (git mv):**
1. `ClaudeStreamRelayService.php` → `AiStreamRelayService.php`
2. `ClaudeRunCleanupService.php` → `AiRunCleanupService.php`
3. `RunClaudeJob.php` → `RunAiJob.php` — update to use `AiProviderInterface` DI resolution
4. `ClaudeQuickHandler.php` → `AiQuickHandler.php`

**Edited files:**
5. `yii/config/main.php` — update component references
6. `ClaudeController.php` — update service references
7. `ProjectController.php` — update service references
8. `PromptInstanceController.php` — update handler references
9. Add `class_alias('app\jobs\RunAiJob', 'app\jobs\RunClaudeJob')` to bootstrap entries (`web/index.php`, `yii` console entry)

**Validation:** Tests pass with updated references.

**Commit:** `REFACTOR: rename Claude services, job, and handler to AI-agnostic names`

---

## P6: Rename AiPermissionMode + Update Project Model

**Goal:** Rename enum and update Project model methods/attributes.

**Renamed files:**
1. `ClaudePermissionMode.php` → `AiPermissionMode.php`

**Edited files:**
2. `Project.php` — rename 9 methods, update attribute labels, update `afterSave()` relevantFields, update `afterSave()`/`afterDelete()` to use DI-resolved provider

**Validation:** Tests pass.

**Commit:** `REFACTOR: rename ClaudePermissionMode to AiPermissionMode, update Project model`

---

## P7: Rename Controllers + Routes + RBAC Config

**Goal:** Rename controllers and update routing/RBAC.

**Renamed files (git mv):**
1. `yii/controllers/ClaudeController.php` → `yii/controllers/AiChatController.php`
2. `yii/commands/ClaudeRunController.php` → `yii/commands/AiRunController.php`
3. `yii/commands/ClaudeController.php` → `yii/commands/AiController.php`

**Edited files:**
4. `yii/config/rbac.php` — update entity keys, permission names, rule class
5. `yii/config/main.php` — update URL rules (`claude/*` → `ai/*`), remove `claudeWorkspaceService` component
6. `yii/config/console.php` — update controller map if needed
7. `ProjectController.php` — rename `actionClaudeCommands` → `actionAiCommands`, `loadClaudeOptions` → `loadAiOptions`
8. `NoteController.php` — rename `actionClaude` → `actionAi`, update redirect

**Validation:** Routes work, RBAC resolves correctly, tests pass.

**Commit:** `REFACTOR: rename controllers and routes from Claude to AI`

---

## P8: Update Views + CSS + Layouts

**Goal:** Rename view directory, update labels to dynamic provider name, update CSS file.

**Renamed:**
1. `yii/views/claude/` → `yii/views/ai-chat/` (entire directory)
2. `yii/web/css/claude-chat.css` → `yii/web/css/ai-chat.css`

**Edited files:**
3. `yii/views/ai-chat/index.php` — update URLs, sessionStorage keys, JS strings, permission modes dropdown
4. `yii/views/ai-chat/runs.php` — update titles, breadcrumbs
5. `yii/views/ai-chat/cleanup-confirm.php` — update breadcrumbs
6. `yii/views/layouts/main.php` — update CSS import, controller ID checks, nav label
7. `yii/views/layouts/_bottom-nav.php` — update controller ID check, label
8. `yii/views/project/_form.php` — rename form field names `claude_options` → `ai_options`
9. `yii/views/note/view.php` — update URL + sessionStorage key
10. `yii/views/note/index.php` — update sessionStorage key
11. `yii/views/prompt-instance/_form.php` — update URL + sessionStorage key
12. `yii/views/prompt-instance/view.php` — update URL + sessionStorage key
13. `yii/views/prompt-instance/index.php` — update sessionStorage key
14. `yii/web/css/mobile.css` — update comment reference

**Validation:** Pages render correctly, no broken links.

**Commit:** `REFACTOR: rename views and CSS from Claude to AI, dynamic provider labels`

---

## P9: DI Wiring, Config Cleanup, Bootstrap

**Goal:** Final config changes, class aliases, cleanup DI.

**Edited files:**
1. `yii/config/main.php` — final DI cleanup, verify all bindings
2. `yii/config/console.php` — DI bindings if separate
3. `yii/config/test.php` — DI bindings for test env
4. `yii/web/index.php` — add `class_alias` for queue job backward compat
5. `yii/yii` (console entry) — add `class_alias`

**Validation:** Full app works, queue processes, DI resolves.

**Commit:** `CHG: finalize DI wiring and bootstrap aliases for AI provider layer`

---

## P10: Rename + Update Tests

**Goal:** Rename all test files and update references.

**Renamed files (17):**
| From | To |
|------|-----|
| `tests/fixtures/ClaudeRunFixture.php` | `tests/fixtures/AiRunFixture.php` |
| `tests/fixtures/data/claude_runs.php` | `tests/fixtures/data/ai_runs.php` |
| `tests/unit/models/ClaudeRunTest.php` | `tests/unit/models/AiRunTest.php` |
| `tests/unit/models/ClaudeRunSearchTest.php` | `tests/unit/models/AiRunSearchTest.php` |
| `tests/unit/models/ClaudeRunQueryTest.php` | `tests/unit/models/AiRunQueryTest.php` |
| `tests/unit/services/ClaudeCliServiceTest.php` | `tests/unit/services/ai/ClaudeCliProviderTest.php` |
| `tests/unit/services/ClaudeWorkspaceServiceTest.php` | _(merge into ClaudeCliProviderTest)_ |
| `tests/unit/services/ClaudeStreamRelayServiceTest.php` | `tests/unit/services/AiStreamRelayServiceTest.php` |
| `tests/unit/services/ClaudeRunCleanupServiceTest.php` | `tests/unit/services/AiRunCleanupServiceTest.php` |
| `tests/unit/enums/ClaudeRunStatusTest.php` | `tests/unit/enums/AiRunStatusTest.php` |
| `tests/unit/rbac/ClaudeRunOwnerRuleTest.php` | `tests/unit/rbac/AiRunOwnerRuleTest.php` |
| `tests/unit/jobs/RunClaudeJobTest.php` | `tests/unit/jobs/RunAiJobTest.php` |
| `tests/unit/controllers/ClaudeControllerTest.php` | `tests/unit/controllers/AiChatControllerTest.php` |
| `tests/unit/commands/ClaudeRunControllerTest.php` | `tests/unit/commands/AiRunControllerTest.php` |
| `tests/unit/handlers/ClaudeQuickHandlerTest.php` | `tests/unit/handlers/AiQuickHandlerTest.php` |

**New test files (3):**
- `tests/unit/services/ai/AiProviderInterfaceTest.php` — verify ClaudeCliProvider implements all interfaces
- `tests/unit/services/ai/MinimalProviderTest.php` — mock minimal provider, verify graceful degradation
- `tests/unit/models/AiRunProviderValidationTest.php` — verify provider regex validation

**Update internal references in:**
- `tests/unit/controllers/ProjectControllerTest.php`
- `tests/unit/services/ClaudeCliCompletionClientTest.php`
- Fixture data: add `provider` column, update `tableName` reference

**Validation:** `vendor/bin/codecept run unit` — all tests pass.

**Commit:** `REFACTOR: rename and update all test files for AI provider abstraction`

---

## P11: Cleanup + Delete Old Files

**Goal:** Remove original files that were copied (not git-mv'd), verify no dangling references.

**Actions:**
1. Delete old service files if they still exist as separate copies:
   - `ClaudeCliService.php`, `ClaudeWorkspaceService.php` (if not already removed when ClaudeCliProvider replaced them)
2. Grep entire codebase for remaining `Claude` references (excluding `.claude/` docs, CSS class names, and comments)
3. Verify no broken `use` imports
4. Rename storage directory: `storage/claude-runs/` → `storage/ai-runs/` (move existing files)
5. Final full test run

**Validation:** Zero `Claude` references in PHP code (except class aliases and CSS). All tests pass.

**Commit:** `DEL: remove deprecated Claude-specific files, finalize AI provider rename`

---

## Dependency Graph

```
P1 (interfaces) ─── standalone, no dependencies
│
P2 (provider)   ─── depends on P1
│
P3 (migrations) ─── standalone (schema only), but P4 must follow
│
P4 (models)     ─── depends on P3 (tables must be renamed first)
│
P5 (services)   ─── depends on P4 (services reference models)
│
P6 (enum+project) ── depends on P5
│
P7 (controllers) ── depends on P5 + P6
│
P8 (views)      ─── depends on P7 (controller IDs must match)
│
P9 (config)     ─── depends on P7 + P8
│
P10 (tests)     ─── depends on P4-P9 (tests reference all layers)
│
P11 (cleanup)   ─── depends on all above
```

**Note:** P1 and P3 can technically run in parallel (no dependencies), but running sequentially is safer for context management.

## Session Instructions

When starting any phase, the agent MUST:

1. Read `impl/todos.md` to see current progress
2. Read only the relevant phase section from this plan
3. Do NOT re-read the full `spec.md` — it's too large for context
4. Commit after completing the phase
5. Update `impl/todos.md` with completion status
6. Run tests before committing

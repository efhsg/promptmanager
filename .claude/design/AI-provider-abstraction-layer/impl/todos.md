# Implementation Progress

## Status: Phase 3 complete

## Phases

- [x] **P1**: Create interfaces (5 new files) — committed: 1e366de
- [x] **P2**: Create ClaudeCliProvider (extract from services) — committed: 20dd7b5
- [x] **P3**: Database migrations (3 migrations) + model/config updates for new column names
- [ ] **P4**: Rename models + enums + RBAC rule
- [ ] **P5**: Rename services + job + handler
- [ ] **P6**: Rename AiPermissionMode + update Project model
- [ ] **P7**: Rename controllers + routes + RBAC config
- [ ] **P8**: Update views + CSS + layouts
- [ ] **P9**: DI wiring, config cleanup, bootstrap
- [ ] **P10**: Rename + update tests
- [ ] **P11**: Cleanup + delete old files

## Current Phase: P3 — Database Migrations (DONE)

### Migrations created
- [x] `yii/migrations/m260217_000001_rename_claude_run_to_ai_run.php` — table rename, provider column, FK/index rename
- [x] `yii/migrations/m260217_000002_rename_claude_columns_in_project.php` — `claude_options` → `ai_options`, `claude_context` → `ai_context`
- [x] `yii/migrations/m260217_000003_rename_claude_rbac_to_ai.php` — RBAC permissions rename, queue channel update

### Additional files created/updated (needed for code to work with new schema)
- [x] `yii/rbac/AiRunOwnerRule.php` — new RBAC rule class (needed by migration 3)
- [x] `yii/models/ClaudeRun.php` — `tableName()` → `'{{%ai_run}}'`
- [x] `yii/models/Project.php` — attribute references `claude_options` → `ai_options`, `claude_context` → `ai_context`
- [x] `yii/config/rbac.php` — permission names `viewClaudeRun` → `viewAiRun`, rule class → `AiRunOwnerRule`
- [x] `yii/config/main.php` — queue channel `'claude'` → `'ai'`
- [x] `yii/controllers/ProjectController.php` — POST key `claude_options` → `ai_options`
- [x] `yii/views/project/_form.php` — form field names `claude_options[...]` → `ai_options[...]`, field `claude_context` → `ai_context`
- [x] `yii/services/projectload/EntityConfig.php` — excluded columns list
- [x] `yii/services/projectload/ProjectLoadService.php` — warning message text
- [x] Tests updated: ClaudeControllerTest, ClaudeWorkspaceServiceTest, SchemaInspectorTest, ProjectLoadServiceTest

### Deviation from plan
- `claude_permission_mode` column does NOT exist (already merged into `claude_options` JSON in earlier migration) — skipped in migration 2
- Additional code changes beyond "just migrations" were required: models, config, views, and tests all reference DB columns directly, so they had to be updated alongside the schema changes

### Validation
- [x] Migrations run on both schemas (yii + yii_test)
- [x] Linter — 0 issues
- [x] Unit tests — 1071 pass, 21 skipped, 0 failures

## Session Log

| Date | Phase | Status | Commit | Notes |
|------|-------|--------|--------|-------|
| 2026-02-17 | P1 | Complete | 1e366de | 5 interfaces created, linter + tests green |
| 2026-02-17 | P2 | Complete | 20dd7b5 | ClaudeCliProvider created, DI registered, tests green |
| 2026-02-17 | P3 | Complete | pending | 3 migrations + model/config/view updates, tests green |

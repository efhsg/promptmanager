# Implementation Progress

## Status: Phase 7 complete

## Phases

- [x] **P1**: Create interfaces (5 new files) — committed: 1e366de
- [x] **P2**: Create ClaudeCliProvider (extract from services) — committed: 20dd7b5
- [x] **P3**: Database migrations (3 migrations) + model/config updates for new column names — committed: ca5a093
- [x] **P4**: Rename models + enums (AiRun, AiRunQuery, AiRunSearch, AiRunStatus) — committed: c6cb258
- [x] **P5**: Rename services + job + handler — committed: 28e09f4
- [x] **P6**: Rename AiPermissionMode + update Project model — committed: c5e8cb1
- [x] **P7**: Rename controllers + routes + RBAC config
- [ ] **P8**: Update views + CSS + layouts
- [ ] **P9**: DI wiring, config cleanup, bootstrap
- [ ] **P10**: Rename + update tests
- [ ] **P11**: Cleanup + delete old files

## Current Phase: P7 — Rename Controllers + Routes + RBAC Config (DONE)

### Renamed files (git mv)
- [x] `controllers/ClaudeController.php` → `controllers/AiChatController.php`
- [x] `commands/ClaudeController.php` → `commands/AiController.php`
- [x] `commands/ClaudeRunController.php` → `commands/AiRunController.php`

### Class renames
- [x] `ClaudeController` (web) → `AiChatController`
- [x] `ClaudeController` (console) → `AiController`
- [x] `ClaudeRunController` → `AiRunController`

### Method renames
- [x] `AiChatController::loadClaudeCommands()` → `loadAiCommands()`
- [x] `ProjectController::actionClaudeCommands()` → `actionAiCommands()`
- [x] `NoteController::actionClaude()` → `actionAi()`

### Config changes
- [x] `rbac.php`: entity key `claude` → `ai-chat`, `claudeRun` → `aiRun`
- [x] `rbac.php`: project entity `claudeCommands` → `aiCommands`
- [x] `rbac.php`: note entity `claude` → `ai`
- [x] `main.php`: removed `claudeWorkspaceService` component
- [x] `main.php`: added URL rule `claude/<action>` → `ai-chat/<action>` for backward compat

### Updated callers
- [x] `ProjectController.php`: redirect in `actionClaude` → `/ai-chat/index`
- [x] `NoteController.php`: redirect in `actionAi` → `/ai-chat/index`

### Test updates
- [x] `ClaudeControllerTest.php`: updated class import, constructor calls, reflection helper
- [x] `ClaudeRunControllerTest.php`: updated class import and constructor calls
- [x] `ProjectControllerTest.php`: updated `actionClaudeCommands` → `actionAiCommands`

### Validation
- [x] Unit tests — 1071 pass, 21 skipped, 0 failures

## Session Log

| Date | Phase | Status | Commit | Notes |
|------|-------|--------|--------|-------|
| 2026-02-17 | P1 | Complete | 1e366de | 5 interfaces created, linter + tests green |
| 2026-02-17 | P2 | Complete | 20dd7b5 | ClaudeCliProvider created, DI registered, tests green |
| 2026-02-17 | P3 | Complete | ca5a093 | 3 migrations + model/config/view updates, tests green |
| 2026-02-17 | P4 | Complete | c6cb258 | 4 file renames + 15 reference edits, tests green |
| 2026-02-17 | P5 | Complete | 28e09f4 | 4 file renames + 9 reference edits + class_alias, tests green |
| 2026-02-17 | P6 | Complete | c5e8cb1 | 1 enum rename + 9 method renames + DI provider in afterSave/afterDelete |
| 2026-02-17 | P7 | Complete | pending | 3 controller renames + RBAC + URL rule + 3 test updates |

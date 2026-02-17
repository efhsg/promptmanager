# Implementation Progress

## Status: Caller migration complete — all legacy services deleted

## Phases

- [x] **P1**: Create interfaces (5 new files) — committed: 1e366de
- [x] **P2**: Create ClaudeCliProvider (extract from services) — committed: 20dd7b5
- [x] **P3**: Database migrations (3 migrations) + model/config updates for new column names — committed: ca5a093
- [x] **P4**: Rename models + enums (AiRun, AiRunQuery, AiRunSearch, AiRunStatus) — committed: c6cb258
- [x] **P5**: Rename services + job + handler — committed: 28e09f4
- [x] **P6**: Rename AiPermissionMode + update Project model — committed: c5e8cb1
- [x] **P7**: Rename controllers + routes + RBAC config — committed: 3406d1b
- [x] **P8**: Update views + CSS + layouts — committed: 294199f
- [x] **P9**: DI wiring, config cleanup, bootstrap — committed: 8281154
- [x] **P10**: Rename + update tests — committed: 4334bb9
- [x] **P11**: Cleanup + delete old files — committed: 2cadc2e
- [x] **P12**: Migrate callers to provider interfaces + delete legacy services

## Current Phase: P12 — Caller Migration (DONE)

### Callers migrated (4 files)
- [x] `controllers/AiChatController.php` — inject `AiProviderInterface` + `CopyFormatConverter`, 7 call sites updated
- [x] `controllers/ProjectController.php` — inject `AiProviderInterface`, 2 call sites updated
- [x] `commands/AiController.php` — inject `AiProviderInterface`, 2 call sites updated
- [x] `jobs/RunAiJob.php` — `createStreamingProvider(): AiStreamingProviderInterface`, 1 call site updated

### Tests updated (3 files)
- [x] `tests/unit/controllers/AiChatControllerTest.php` — mock `ClaudeCliProvider`, rename methods
- [x] `tests/unit/controllers/ProjectControllerTest.php` — mock `ClaudeCliProvider`, rename methods
- [x] `tests/unit/jobs/RunAiJobTest.php` — mock `AiStreamingProviderInterface`, rename factory method

### Legacy files deleted (4 files)
- [x] `services/ClaudeCliService.php` — DELETED
- [x] `services/ClaudeWorkspaceService.php` — DELETED
- [x] `tests/unit/services/ClaudeCliServiceTest.php` — DELETED
- [x] `tests/unit/services/ClaudeWorkspaceServiceTest.php` — DELETED

### Files kept (correct)
- `services/ClaudeCliCompletionClient.php` — already uses `AiProviderInterface`, implements `AiCompletionClient`
- `rbac/ClaudeRunOwnerRule.php` — migration `safeDown()` dependency
- `services/ai/providers/ClaudeCliProvider.php` — IS the Claude-specific provider

### Remaining `Claude` references (all correct)
1. **Provider-specific**: `ClaudeCliProvider.php` — IS the Claude provider
2. **Completion client**: `ClaudeCliCompletionClient` — Claude-specific impl of `AiCompletionClient`
3. **RBAC rule**: `ClaudeRunOwnerRule` — migration rollback safety
4. **UI labels**: "Claude CLI", "Claude thinking" in views — product name
5. **Docblocks**: Historical references in `ClaudeCliProvider` — describes provenance

### Validation
- [x] Linter — 0 fixes needed on all 7 changed files
- [x] Unit tests — 1002 pass, 21 skipped, 0 failures (69 tests removed with deleted files)
- [x] No remaining `ClaudeCliService` or `ClaudeWorkspaceService` imports in production code

## Session Log

| Date | Phase | Status | Commit | Notes |
|------|-------|--------|--------|-------|
| 2026-02-17 | P1 | Complete | 1e366de | 5 interfaces created, linter + tests green |
| 2026-02-17 | P2 | Complete | 20dd7b5 | ClaudeCliProvider created, DI registered, tests green |
| 2026-02-17 | P3 | Complete | ca5a093 | 3 migrations + model/config/view updates, tests green |
| 2026-02-17 | P4 | Complete | c6cb258 | 4 file renames + 15 reference edits, tests green |
| 2026-02-17 | P5 | Complete | 28e09f4 | 4 file renames + 9 reference edits + class_alias, tests green |
| 2026-02-17 | P6 | Complete | c5e8cb1 | 1 enum rename + 9 method renames + DI provider in afterSave/afterDelete |
| 2026-02-17 | P7 | Complete | 3406d1b | 3 controller renames + RBAC + URL rule + 3 test updates |
| 2026-02-17 | P8 | Complete | 294199f | 2 renames + 16 view edits + 2 CSS edits, all routes updated |
| 2026-02-17 | P9 | Complete | 8281154 | 2 comment updates in main.php, DI already wired |
| 2026-02-17 | P10 | Complete | 4334bb9 | 14 test file renames + 4 class/reference updates |
| 2026-02-17 | P11 | Complete | 2cadc2e | Storage dir rename + cleanup documentation |
| 2026-02-17 | P12 | Complete | pending | 4 callers migrated, 4 legacy files deleted, 1002 tests green |

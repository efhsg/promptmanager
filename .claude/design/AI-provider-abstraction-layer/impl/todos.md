# Implementation Progress

## Status: Phase 11 complete (all phases done)

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
- [x] **P11**: Cleanup + delete old files

## Current Phase: P11 — Cleanup + Delete Old Files (DONE)

### Storage directory rename
- [x] `storage/claude-runs/` → `storage/ai-runs/` (5 PHP references updated)
- [x] `.gitignore` comment updated

### Files NOT deleted (still actively used)
These services are still directly imported by controllers, commands, and jobs.
Callers haven't been migrated to use provider interfaces yet.
- `services/ClaudeCliService.php` — imported by 5 non-test files
- `services/ClaudeWorkspaceService.php` — imported by AiController
- `services/ClaudeCliCompletionClient.php` — registered in DI config
- `rbac/ClaudeRunOwnerRule.php` — migration safeDown() dependency

### Remaining `Claude` references (categorized)
1. **Provider-specific (correct)**: `ClaudeCliProvider.php` — IS the Claude provider, name is intentional
2. **Old services (future work)**: `ClaudeCliService`, `ClaudeWorkspaceService`, `ClaudeCliCompletionClient` — need caller migration before deletion
3. **RBAC rule (migration dep)**: `ClaudeRunOwnerRule` — kept for migration rollback safety
4. **UI labels (correct)**: "Claude CLI", "Claude thinking" etc. in views — product name, not code naming
5. **Comments/docblocks (correct)**: References to "Claude CLI" in services/jobs — describes the tool being used

### Validation
- [x] Unit tests — 1071 pass, 21 skipped, 0 failures
- [x] No remaining `claude-runs` storage path references in PHP code
- [x] No broken `use` imports

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
| 2026-02-17 | P11 | Complete | pending | Storage dir rename + cleanup documentation |

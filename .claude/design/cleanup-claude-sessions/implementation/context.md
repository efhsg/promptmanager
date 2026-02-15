# Context

## Goal
Add cleanup functionality to Claude Sessions page (`/claude/runs`) — single session delete + bulk cleanup of all terminal sessions.

## Scope
- New: `ClaudeRunCleanupService` — delete logic with stream file cleanup
- Modify: `ClaudeController` — add `actionDeleteSession()`, `actionCleanup()`, inject service
- Modify: `runs.php` — add delete column + cleanup button
- New: `cleanup-confirm.php` — bulk cleanup confirmation page
- New: `ClaudeRunCleanupServiceTest` — unit tests

## Key References
- Spec: `.claude/design/cleanup-claude-sessions/spec.md`
- Model: `yii/models/ClaudeRun.php` — `getStreamFilePath()`, `isTerminal()`, `getSessionRunCount()`, `getSessionLatestStatus()`
- Query: `yii/models/query/ClaudeRunQuery.php` — `forUser()`, `terminal()`, `forSession()`
- Controller: `yii/controllers/ClaudeController.php` — DI constructor with 4 services
- View: `yii/views/claude/runs.php` — GridView with session aggregates
- Confirm template: `yii/views/project/delete-confirm.php`
- Service pattern: `yii/services/ClaudeStreamRelayService.php` — plain class, no Component

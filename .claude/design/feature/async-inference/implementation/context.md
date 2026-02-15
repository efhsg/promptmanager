# Async Inference — Implementation Context

## Goal
Decouple Claude CLI inference from the browser session. Inference runs as a background job (via yii2-queue DB driver), survives browser close, and can be reconnected via SSE stream relay.

## Scope
- New: ClaudeRunStatus enum, ClaudeRun model + query, RunClaudeJob, ClaudeStreamRelayService, ClaudeRunOwnerRule, ClaudeRunController (console), migration
- Modify: ClaudeCliService (4 changes), ClaudeController (5 new actions + wrapper), config/main.php (queue), config/rbac.php, docker-compose.yml, composer.json
- Frontend: two-step send flow, reconnect, cancel via runId, active runs badge
- Tests: unit tests for model, query, enum, job, relay service, cleanup commands

## Key References
- Spec: `.claude/design/feature/async-inference/spec.md`
- Existing patterns: Note model, NoteQuery, NoteOwnerRule, NoteType enum
- ClaudeController: `yii/controllers/ClaudeController.php`
- ClaudeCliService: `yii/services/ClaudeCliService.php`
- View: `yii/views/claude/index.php`

# Implementation Context: plug-in-codex

## Goal

Make adding new AI CLI providers as simple as implementing a provider class and registering it in DI config. Each provider is configurable per-project via its own tab in project settings, with provider-specific options driven by `getConfigSchema()`.

## Scope

### In scope
- `getConfigSchema()` on `AiConfigProviderInterface`
- Namespaced per-provider `ai_options` storage in Project model
- Per-provider tabs in project form (dynamic)
- Provider-specific options flow through to CLI via `buildCommand()`
- Chat view dynamic custom fields from configSchema
- RunAiJob stream parsing delegation to provider
- Frontend event router for provider-specific streaming
- CodexCliProvider as proof-of-concept second provider
- Docker setup for Codex binary
- Workspace management per provider (path change to `{id}/claude/`)

### Out of scope
- Codex workspace management (no `AiWorkspaceProviderInterface` for Codex initially)
- New database migrations (reuses existing `ai_options` JSON column)
- Changes to AiRun model (already has `provider` column)

## Key File References

| File | Role |
|------|------|
| `yii/services/ai/AiConfigProviderInterface.php` | Interface to extend |
| `yii/services/ai/providers/ClaudeCliProvider.php` | Reference implementation (1125 lines) |
| `yii/models/Project.php` | Model with `ai_options` JSON (608 lines) |
| `yii/controllers/ProjectController.php` | Project CRUD (289 lines) |
| `yii/controllers/AiChatController.php` | Chat + run creation (1225 lines) |
| `yii/jobs/RunAiJob.php` | Job execution (376 lines) |
| `yii/views/project/_form.php` | Project form (674 lines) |
| `yii/views/ai-chat/index.php` | Chat view (3592 lines) |
| `yii/config/main.php` | DI config (190 lines) |
| `yii/services/ai/AiProviderRegistry.php` | Provider registry (70 lines) |

## Definition of Done

- All spec requirements implemented per phase
- Existing tests updated and green
- New tests for new functionality
- Linter passes with 0 issues
- Backward compatible with existing single-provider usage
- Legacy flat `ai_options` auto-detected and migrated on save

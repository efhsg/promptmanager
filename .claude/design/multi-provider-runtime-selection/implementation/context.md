# Context — Multi-Provider Runtime Selection

## Goal
Extend AI provider abstraction with a registry that manages multiple simultaneously registered providers. Add UI selector for per-request provider selection. Queue job resolves correct provider from `AiRun.provider` column.

## Scope
- New: `AiProviderRegistry` service
- Modified: `AiConfigProviderInterface` (add `getSupportedModels()`)
- Modified: `ClaudeCliProvider` (implement `getSupportedModels()`)
- Modified: `AiChatController` (inject registry, provider selection from request)
- Modified: `RunAiJob` (resolve provider from run record via registry)
- Modified: `main.php` DI config (registry + provider registrations)
- Modified: `views/ai-chat/index.php` (provider dropdown, dynamic models/modes)
- New: `AiProviderRegistryTest`
- Modified: `RunAiJobTest` (adapt to `resolveProvider()`)

## Key References
- Spec: `.claude/design/multi-provider-runtime-selection/spec.md`
- Reviews: `.claude/design/multi-provider-runtime-selection/reviews.md`
- Interfaces: `yii/services/ai/Ai*Interface.php`
- Provider: `yii/services/ai/providers/ClaudeCliProvider.php`
- Controller: `yii/controllers/AiChatController.php`
- Job: `yii/jobs/RunAiJob.php`
- View: `yii/views/ai-chat/index.php`
- DI Config: `yii/config/main.php`

## Key Decisions (from reviews)
- Use closure-based DI config (not `Instance::of()`) to avoid `setResolveArrays` issue
- Register `AiProviderRegistry` as singleton via `setSingleton()`
- Sync fallback NDJSON: no `subtype` key (not consumed by existing parsing)
- `actionCancel()` calls `cancelProcess()` on ALL providers (PIDs are unique)
- Session warning is persistent (not auto-dismiss)
- Element IDs renamed: `claude-model` → `ai-model`, `claude-permission-mode` → `ai-permission-mode`

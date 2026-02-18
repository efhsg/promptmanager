# Review Context

## Goal
Write a complete, implementation-ready specification for the "Multi-Provider Runtime Selection" feature.

## Scope
Extend the AI provider abstraction layer to support multiple simultaneously registered providers with runtime selection via the web UI. Currently only one provider (ClaudeCliProvider) is registered in DI. This feature adds a registry layer on top, a UI selector, and queue job wiring.

## User Story
As a user, I want to select which AI CLI provider (Claude, Codex, Gemini) to use from a dropdown in the chat interface, so that I can choose the most suitable model for each task without reconfiguring the application.

## Key References
- Interfaces: `yii/services/ai/AiProviderInterface.php`, `AiStreamingProviderInterface.php`, `AiConfigProviderInterface.php`, `AiUsageProviderInterface.php`, `AiWorkspaceProviderInterface.php`
- Provider: `yii/services/ai/providers/ClaudeCliProvider.php`
- Controller: `yii/controllers/AiChatController.php`
- Queue job: `yii/jobs/RunAiJob.php`
- DI config: `yii/config/main.php` (lines 151-156)
- View: `yii/views/ai-chat/index.php` (hardcoded models, permission modes, title)
- Model: `yii/models/AiRun.php` (provider column already exists)
- Completion: `yii/services/ClaudeCliCompletionClient.php`, `yii/handlers/AiQuickHandler.php`
- Previous spec: `.claude/design/AI-provider-abstraction-layer/spec.md`

# Command Substitution — Implementation Context

## Goal
Replace `/command-name` patterns in prompts with natural-language instructions for providers that don't support native slash commands (e.g. Codex CLI).

## Scope
- New service: `PromptCommandSubstituter`
- Interface change: `supportsSlashCommands(): bool` on `AiConfigProviderInterface`
- Integration in `RunAiJob::execute()` after `claimForProcessing()`
- Unit tests for the substituter and provider method

## Key References
- `yii/services/ai/AiConfigProviderInterface.php` — interface to extend
- `yii/services/ai/providers/ClaudeCliProvider.php` — returns `true`
- `yii/services/ai/providers/CodexCliProvider.php` — returns `false`
- `yii/jobs/RunAiJob.php` — integration point (after line 100)
- `yii/services/ai/AiProviderRegistry.php` — `all()` for finding capable provider
- `.claude/design/feature/command-substitution/plan.md` — full spec

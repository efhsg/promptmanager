# Command Substitution — Implementation Steps

- [x] Add `supportsSlashCommands(): bool` to `AiConfigProviderInterface`
- [x] Implement `supportsSlashCommands()` in `ClaudeCliProvider` → `true`
- [x] Implement `supportsSlashCommands()` in `CodexCliProvider` → `false`
- [x] Create `yii/services/ai/PromptCommandSubstituter.php`
- [x] Create `yii/tests/unit/services/ai/PromptCommandSubstituterTest.php`
- [x] Integrate substitution in `RunAiJob::execute()` + `loadAvailableCommands()`
- [x] Add `testSupportsSlashCommandsReturnsFalse` to `CodexCliProviderTest`
- [x] Run linter (0 issues) and full test suite (0 failures)

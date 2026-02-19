# Command Substitution — Insights

## Decisions
- No `ClaudeCliProviderTest` created — file doesn't exist and creating one just for `testSupportsSlashCommandsReturnsTrue` is unnecessary. The plan said "als bestaand" (if existing).
- `loadAvailableCommands()` placed as private method on `RunAiJob` per plan, using registry iteration to find a capable provider.

## Pitfalls
- (none encountered)

## Final Results
- Linter: 0 issues on all modified files
- Tests: 1144 total, 0 errors, 0 failures, 21 skipped (pre-existing)
- New tests: 10 (PromptCommandSubstituterTest) + 1 (CodexCliProviderTest) = 11 added

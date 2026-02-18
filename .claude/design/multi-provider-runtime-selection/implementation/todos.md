# Todos — Multi-Provider Runtime Selection

## Implementation Steps

- [x] 1. Add `getSupportedModels()` to `AiConfigProviderInterface`
- [x] 2. Implement `getSupportedModels()` in `ClaudeCliProvider`
- [x] 3. Create `AiProviderRegistry` service
- [x] 4. Create `AiProviderRegistryTest` unit test
- [x] 5. Update DI config in `main.php`
- [x] 6. Update `AiChatController` — inject registry, provider selection
- [x] 7. Update `RunAiJob` — `resolveProvider()` + sync fallback
- [x] 8. Update `RunAiJobTest` — adapt to new method
- [x] 9. Update view `index.php` — provider dropdown, dynamic models/modes, JS
- [x] 10. Run linter + tests, fix issues

## Final Results

- Linter: 0 issues across all 9 modified PHP files
- Tests: 1083/1083 pass (0 errors, 0 failures, 21 skipped — pre-existing)
- Key test suites: 11 AiProviderRegistryTest + 19 RunAiJobTest + 35 AiChatControllerTest = 65 directly relevant tests

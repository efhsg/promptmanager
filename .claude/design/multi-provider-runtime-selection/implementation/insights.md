# Insights — Multi-Provider Runtime Selection

## Decisions
- DI uses closures (not `Instance::of()`) because Yii2 doesn't resolve Instance objects in nested arrays without `setResolveArrays(true)`
- Registry registered as singleton to prevent multiple instantiations per request
- `actionCancel()` (legacy stream-token endpoint) calls `cancelProcess()` on all providers since there's no run record to determine which provider owns the process
- `AiStreamingProviderInterface` does not extend `AiProviderInterface` — they are separate interfaces. Mocks for tests that need both must use `createMockForIntersectionOfInterfaces()`

## Findings
- `ClaudeCliProviderTest.php` does not exist in the codebase. The spec mentioned extending it but there's nothing to extend. The `getSupportedModels()` method is trivial enough that testing it via the registry test is sufficient.
- The view file (`index.php`) is ~2900 lines. All JS is inline (no separate modules), following existing architecture.
- Element IDs renamed from `claude-model`/`claude-permission-mode` to `ai-model`/`ai-permission-mode` — global replace across the entire view file.

## Pitfalls
- Transient MySQL deadlocks during fixture loading affect test reliability. This is a pre-existing infrastructure issue, not related to our changes. The deadlock occurs on `project` table INSERT during `ProjectFixture` loading and is non-deterministic.
- The `send()` function must dismiss the provider switch warning on first send (clears the DOM element). This was added to prevent stale warnings persisting after the user sends a message with the new provider.

## Files Changed
- `yii/services/ai/AiConfigProviderInterface.php` — added `getSupportedModels()` method
- `yii/services/ai/providers/ClaudeCliProvider.php` — implemented `getSupportedModels()`
- `yii/services/ai/AiProviderRegistry.php` — NEW: provider registry service
- `yii/tests/unit/services/ai/AiProviderRegistryTest.php` — NEW: 11 unit tests
- `yii/config/main.php` — singleton registry + closure-based DI definitions
- `yii/controllers/AiChatController.php` — inject registry, provider resolution, cross-provider session detection
- `yii/jobs/RunAiJob.php` — resolveProvider() from registry, streaming/sync branching
- `yii/tests/unit/jobs/RunAiJobTest.php` — adapted to resolveProvider(), 3 new tests (provider not found, sync fallback, streaming check), intersection mock for dual interfaces
- `yii/tests/unit/controllers/AiChatControllerTest.php` — adapted to registry injection, added `configureProviderMockDefaults()` helper
- `yii/views/ai-chat/index.php` — provider dropdown, dynamic model/permission repopulation, capability badges, provider query params, JS helpers (repopulateSelect, showProviderSwitchWarning, updateCapabilityBadges)

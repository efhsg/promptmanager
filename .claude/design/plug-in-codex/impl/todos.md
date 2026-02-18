# Implementation Todos

## Status: Phase 4 complete — ready to commit

## Phases
- [x] **P1**: Backend Foundation — Interface, Model, Config, RunAiJob Refactor
- [x] **P2**: Controllers & CodexCliProvider
- [x] **P3**: Project Form View — Per-Provider Tabs
- [x] **P4**: Chat View Frontend — Dynamic Options, Event Router, Docker

## Completed Phase: P1 — Backend Foundation

- [x] Add `getConfigSchema(): array` to `AiConfigProviderInterface`
- [x] Implement `ClaudeCliProvider::getConfigSchema()` (allowedTools, disallowedTools, appendSystemPrompt)
- [x] Update `ClaudeCliProvider` workspace path from `{id}/` to `{id}/claude/` with migration logic
- [x] Add `Project::getAiOptionsForProvider()`, `setAiOptionsForProvider()`, `getDefaultProvider()`, `isNamespacedOptions()`
- [x] Update `Project::getAiOptions()` for backward compatibility with namespaced storage
- [x] Make `Project::getAiCommandBlacklist()` and `getAiCommandGroups()` accept optional provider parameter
- [x] Make `Project::afterSave()` provider-aware via `AiProviderRegistry`
- [x] Make `Project::afterDelete()` provider-aware via `AiProviderRegistry`
- [x] Refactor `RunAiJob` — delegate stream parsing to `parseStreamResult()`, remove extract methods, add sync fallback event
- [x] Create `ProjectAiOptionsTest` for namespaced ai_options methods
- [x] Update `RunAiJobTest` for delegated parsing flow
- [x] Run linter + fix issues (0 issues)
- [x] Run unit tests + fix failures (1108 pass, 0 fail, 21 skipped)

## Completed Phase: P2 — Controllers & CodexCliProvider

- [x] Refactor `ProjectController` DI: `AiProviderInterface` → `AiProviderRegistry`
- [x] Make `ProjectController::loadAiOptions()` per-provider via `setAiOptionsForProvider()`
- [x] Make `ProjectController::actionAiCommands()` accept optional `provider` parameter
- [x] Make `ProjectController::actionUpdate()` config status provider-aware (per-provider check)
- [x] Refactor `AiChatController::prepareRunRequest()`: remove `allowedKeys` whitelist, use `getAiOptionsForProvider()`
- [x] Add `configSchema` to `AiChatController::buildProviderData()`
- [x] Create `CodexCliProvider` implementing `AiProviderInterface`, `AiStreamingProviderInterface`, `AiConfigProviderInterface`
- [x] Register `CodexCliProvider` in `yii/config/main.php` as `aiProvider.codex`
- [x] Create `CodexCliProviderTest` (21 tests)
- [x] Update `ProjectControllerTest` for `AiProviderRegistry` constructor change
- [x] Run linter (0 issues)
- [x] Run unit tests (1129 pass, 0 fail, 21 skipped)

## Completed Phase: P3 — Project Form View — Per-Provider Tabs

- [x] Add `buildProviderViewData()` to `ProjectController` — builds provider data for form view
- [x] Pass `$providers` to form view from `actionCreate()` and `actionUpdate()`
- [x] Update `create.php` and `update.php` to pass `$providers` to `_form.php`
- [x] Refactor `_form.php`: replace hardcoded Claude card with dynamic multi-provider/single-provider layout
- [x] Create `_form_provider_options.php` partial — model dropdown, permission mode, config schema fields, command dropdown
- [x] Create `_form_context.php` partial — per-provider config status alerts, Quill editor, generate button
- [x] Form fields namespaced as `ai_options[{provider_id}][{option_key}]`
- [x] Multiple providers: Bootstrap 5 tabs (provider tabs + Context tab)
- [x] Single provider: collapsible cards (current UX preserved)
- [x] Config schema fields rendered dynamically (select, text, textarea, checkbox)
- [x] Command dropdown per-provider with provider-specific AJAX URL
- [x] Permission mode dropdown hidden when `getSupportedPermissionModes()` returns empty
- [x] Run linter + fix issues (1 auto-fixed indentation in _form_provider_options.php)
- [x] Run unit tests (1129 pass, 0 fail, 21 skipped)

## Completed Phase: P4 — Chat View Frontend — Dynamic Options, Event Router, Docker

- [x] Change `$projectDefaults` from flat `getAiOptions()` to per-provider map via `getAiOptionsForProvider()`
- [x] Add `#provider-custom-fields` container div in chat HTML
- [x] Update `prefillFromDefaults()` to be provider-aware + render/prefill custom fields
- [x] Update `getOptions()` to include custom field values from `data-option-key` elements
- [x] Add `renderProviderCustomFields(providerId)` — dynamic select/text/textarea/checkbox from `configSchema`
- [x] Add `prefillCustomFields(providerId)` — prefill custom fields from per-provider defaults
- [x] Update provider change handler to render/prefill custom fields on switch
- [x] Implement event router in `onStreamEvent()` — meta events first, then `_eventHandlers` dispatch
- [x] Add Claude handler in `_eventHandlers` (system/init, stream_event, assistant, result)
- [x] Add Codex handler in `_eventHandlers` (thread.started, item.completed, turn.completed, error)
- [x] Add `onCodexThreadStarted()`, `onCodexItemCompleted()`, `onCodexTurnCompleted()` handlers
- [x] Update config badge from hardcoded "CLAUDE.md"/"claude/" to generic "config file"/"config dir"
- [x] Add `~/.codex/` volume mount to `pma_yii` and `pma_queue` in `docker-compose.yml`
- [x] Add Node.js 22 LTS installation to Dockerfile
- [x] Add `npm install -g @openai/codex` to Dockerfile
- [x] Run linter (0 issues)
- [x] Run unit tests (1129 pass, 0 fail, 21 skipped)

## Session Log

| Date | Phase | Commit | Notes |
|------|-------|--------|-------|
| 2026-02-18 | P1 | be24d55 | All steps complete, linter clean, tests green |
| 2026-02-18 | P2 | 9eef61f | All steps complete, linter clean, tests green |
| 2026-02-18 | P3 | c127395 | All steps complete, linter clean, tests green |
| 2026-02-18 | P4 | pending | All steps complete, linter clean, tests green |

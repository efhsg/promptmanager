# Implementation Plan: plug-in-codex

## Scope

17+ files (new + modified + tests), classification: **L** (Large), 4 phases of max 12 files each.

## Execution Rules

1. One phase per session
2. Commit after each phase — app must work after every commit
3. Run tests before committing
4. Read impl/todos.md first — every session
5. Only read the current phase section, not the full spec

## Phases

### P1: Backend Foundation — Interface, Model, Config, RunAiJob Refactor

**Files:**
1. `yii/services/ai/AiConfigProviderInterface.php` — add `getConfigSchema()` method
2. `yii/services/ai/providers/ClaudeCliProvider.php` — implement `getConfigSchema()`, update workspace path to `{id}/claude/`, add workspace migration logic
3. `yii/models/Project.php` — add `getAiOptionsForProvider()`, `setAiOptionsForProvider()`, `getDefaultProvider()`, `isNamespacedOptions()`, make `afterSave()`/`afterDelete()` provider-aware, make `getAiCommandBlacklist()`/`getAiCommandGroups()` accept optional provider param
4. `yii/jobs/RunAiJob.php` — delegate stream parsing to provider via `parseStreamResult()`, remove `extractResultText()`, `extractMetadata()`, `extractSessionId()`, add sync fallback event
5. `yii/config/main.php` — prepare for CodexCliProvider registration (no-op placeholder comment)
6. `yii/tests/unit/models/ProjectAiOptionsTest.php` — new test for namespaced ai_options methods
7. `yii/tests/unit/jobs/RunAiJobTest.php` — update tests for delegated parsing flow

**Depends on:** none
**Validation:** All existing tests pass, new Project ai_options tests pass, RunAiJob tests updated and green
**Commit message:** `ADD: provider-aware ai_options, config schema interface, RunAiJob stream delegation`

### P2: Controllers & CodexCliProvider

**Files:**
1. `yii/controllers/ProjectController.php` — DI → `AiProviderRegistry`, refactor `loadAiOptions()` per-provider, `actionAiCommands()` provider param, `projectConfigStatus` provider-aware
2. `yii/controllers/AiChatController.php` — `prepareRunRequest()` remove whitelist + per-provider defaults, `buildProviderData()` add configSchema
3. `yii/services/ai/providers/CodexCliProvider.php` — new provider implementing AiProviderInterface, AiStreamingProviderInterface, AiConfigProviderInterface
4. `yii/config/main.php` — register CodexCliProvider singleton + add to registry
5. `yii/tests/unit/services/ai/providers/CodexCliProviderTest.php` — new test for Codex provider

**Depends on:** P1
**Validation:** All tests pass, CodexCliProvider builds valid commands, controllers handle multi-provider
**Commit message:** `ADD: CodexCliProvider, multi-provider controllers, per-provider config flow`

### P3: Project Form View — Per-Provider Tabs

**Files:**
1. `yii/views/project/_form.php` — dynamic per-provider tabs (or single-provider card), model/permissionMode/configSchema fields from provider, command dropdown per provider

**Depends on:** P2
**Validation:** Form renders correctly with single and multiple providers, form submission saves per-provider options
**Commit message:** `ADD: dynamic per-provider tabs in project form`

### P4: Chat View Frontend — Dynamic Options, Event Router, Docker

**Files:**
1. `yii/views/ai-chat/index.php` — configSchema rendering, event router for provider-specific handlers, provider-aware config badge, getOptions()/prefillFromDefaults() updates
2. `docker-compose.yml` — `~/.codex/` mount on pma_yii and pma_queue
3. `docker/yii/Dockerfile` — install `@openai/codex` globally via npm

**Depends on:** P2
**Validation:** Chat view shows dynamic provider options, event routing works, Docker builds successfully
**Commit message:** `ADD: chat view provider options, event routing, Docker Codex support`

## Dependency Graph

```
P1 (Backend Foundation)
 ├── P2 (Controllers & CodexCliProvider)
 │    ├── P3 (Project Form View)
 │    └── P4 (Chat View & Docker)
```

P3 and P4 are independent of each other and could be done in either order.

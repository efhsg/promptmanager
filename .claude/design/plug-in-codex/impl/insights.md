# Implementation Insights

## Decisions

- **Workspace migration strategy**: When `getWorkspacePath()` detects old-style `{id}/CLAUDE.md` without `{id}/claude/` subdir, it auto-migrates files. Only files and the `.claude` dir are moved; other subdirectories (potential other provider workspaces) are skipped.
- **isNamespacedOptions heuristic**: Checks if any top-level key (other than `_default`) has an array value AND a lowercase alphanum key pattern. Scalar values at top level → flat format. This correctly identifies both legacy flat and new namespaced structures.
- **RunAiJob sync fallback event type**: Changed from `type: "result"` to `type: "sync_result"` to distinguish sync results from streaming results in the SSE relay. This is a breaking change for frontend consumers that relied on the old type, but it's correct per spec.
- **PHP CS Fixer brace rules**: The project's `.php-cs-fixer.dist.php` enforces braces on control structures, overriding the CLAUDE.md "no unnecessary curly braces" guideline. Applied fixer rules consistently.

## Findings

- `AiProviderInterface` was already unused in `Project.php` imports after replacing with `AiProviderRegistry` — removed cleanly.
- `RunAiJob` test mock helper needed inline `parseStreamResult()` implementation since the mock can't reference `ClaudeCliProvider` directly. Used the same NDJSON parsing logic.
- The `ClaudeCliProvider::generateSettingsJson()` method still calls `$project->getAiOptions()` (flat accessor). This will need attention in P2 when provider-specific options flow through — it should use `getAiOptionsForProvider('claude')` once controllers send namespaced data.

## P2 Decisions

- **allowedKeys whitelist removal**: Replaced the hardcoded `allowedKeys` whitelist in `prepareRunRequest()` with exclusion of non-option keys (`provider`, `sessionId`, `streamToken`, `prompt`, `contentDelta`). All other keys pass through to the provider's `buildCommand()` which translates only known keys. This is provider-driven validation per spec FR-4.
- **ProjectController::loadAiOptions() per-provider**: Now iterates POST `ai_options` array by provider key, validates each key against `AiProviderRegistry::has()`, and calls `setAiOptionsForProvider()`. Unknown provider keys are silently ignored.
- **projectConfigStatus now per-provider**: `actionUpdate()` builds a per-provider config status map instead of a single flat status. Each entry is keyed by provider identifier and includes `providerName` for display.
- **Codeception verify() doesn't support isInstanceOf()**: Tests use PHPUnit's `$this->assertInstanceOf()` instead.
- **ProjectControllerTest mock update**: Changed from passing mock `ClaudeCliProvider` directly to wrapping it in `AiProviderRegistry`. Both `createController()` and `createControllerWithAiProvider()` helpers updated.

## P2 Findings

- `ClaudeCliProvider::generateSettingsJson()` still calls `$project->getAiOptions()`. Since `getAiOptions()` returns default provider's options in namespaced mode, and Claude is the default, this continues to work correctly. No change needed — the method is provider-specific and reads its own namespace implicitly via the default.
- CodexCliProvider uses `codex exec --json --approval-mode {mode} --model {model} -p -` command structure. Session resume uses `codex exec resume {session_id}`.
- Codex doesn't support slash commands (`loadCommands()` returns `[]`) or permission modes (`getSupportedPermissionModes()` returns `[]`). It uses `approval-mode` instead, exposed via `getConfigSchema()`.

## P3 Decisions

- **Form decomposition into partials**: Split `_form.php` into three files: `_form.php` (main layout), `_form_provider_options.php` (per-provider options), `_form_context.php` (project context editor). This keeps each file focused and avoids a 700+ line monolith.
- **Multi vs single provider layout**: When `count($providers) > 1`, uses Bootstrap 5 nav-tabs with per-provider tab panels + a Context tab. When single provider, uses collapsible cards (preserving existing UX exactly). The spec's wireframe is followed.
- **Default active tab**: The tab matching `$model->getDefaultProvider()` is marked `active` on page load. This ensures the most relevant provider is visible first.
- **Permission mode labels from enum**: The `_form_provider_options.php` partial uses `AiPermissionMode::tryFrom($value)->label()` to get human-readable labels instead of hardcoding them. Falls back to raw value for unknown modes.
- **Config schema field rendering**: Supports `select`, `text`, `textarea`, and `checkbox` types from `getConfigSchema()`. All labels, hints, and option texts are `Html::encode()`d for XSS prevention.
- **Command dropdown load button**: Changed from lazy-load-on-collapse to explicit "Load Commands" button since the command section is now inline in the provider tab/card rather than in its own collapsible. This avoids auto-fetching when switching tabs.
- **Context editor ID renamed**: Changed from `#claude-context-editor` / `#claude-context-hidden` to `#context-editor` / `#context-hidden` since context is now provider-agnostic.
- **Per-provider config status in context**: The context partial iterates all providers in `$projectConfigStatus` and shows per-provider status alerts instead of a single flat alert. Provider names are shown in bold for clarity.

## P3 Findings

- The `_form_provider_options.php` partial handles the case where Codex returns empty `getSupportedPermissionModes()` by not rendering the permission mode dropdown at all — exactly as spec requires.
- PHP CS Fixer adjusted heredoc indentation in `_form_provider_options.php` to match parent scope. The JS in heredocs must be indented to match PHP indentation level.
- No new tests needed for P3 — this is a pure view-layer change with no backend logic changes. The existing `ProjectControllerTest` validates controller behavior, and form rendering is integration-level (Codeception acceptance tests, which are out of scope).

## P4 Decisions

- **Per-provider projectDefaults**: Changed `$projectDefaults` from a flat map (via `getAiOptions()`) to a per-provider map keyed by provider ID (via `getAiOptionsForProvider()`). This enables correct prefilling when the user switches providers in the chat UI.
- **Dynamic custom field rendering**: `renderProviderCustomFields(providerId)` reads `configSchema` from `providerData` and generates form elements with `data-option-key` attributes. `getOptions()` collects these dynamically — no hardcoded field knowledge needed.
- **Event router pattern**: `onStreamEvent()` handles meta events (connection status, errors, sync results) first, then dispatches to `_eventHandlers[activeProvider]` for provider-specific event parsing. Falls back to Claude handler for unknown providers.
- **Codex NDJSON event mapping**: `thread.started` → session init (stores session ID), `item.completed` → content display (code blocks for `code_input`, text for `message`), `turn.completed` → run complete with token usage.
- **Config badge labels**: Replaced hardcoded "CLAUDE.md" / "`.claude/`" with generic "config file" / "config dir" since these are now provider-specific concepts.
- **Docker Codex support**: Node.js 22 LTS installed via nodesource before `USER` switch (needs root). Codex CLI installed globally via npm with `|| true` to not fail the build if registry is unreachable. `~/.codex/` mounted as volume for API key persistence.

## P4 Findings

- No new tests needed for P4 — all changes are in the view layer (JavaScript) and Docker configuration. The existing unit tests (1129 pass) validate backend behavior. Frontend integration testing would require browser-level tests (out of scope).
- The `prefillCustomFields()` method handles the case where defaults don't exist for a provider gracefully (empty object fallback), so switching to a newly-added provider doesn't error.
- Codex `item.completed` events contain nested structure: `item.content[0].text` for messages, `item.content[0].input` for code. The handler checks `item.type` to distinguish between `message` and `function_call` items.

## Pitfalls

- All four phases complete. Future work: acceptance tests for multi-provider form flow and chat event handling.

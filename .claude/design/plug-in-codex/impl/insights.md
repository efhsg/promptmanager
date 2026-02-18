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

## Pitfalls

- **P3 form view** must handle the new per-provider `projectConfigStatus` structure (keyed by provider id) instead of the previous flat structure.
- **P4 chat view** must render `configSchema` fields dynamically and include their values in `getOptions()` for the provider-driven option flow to work.

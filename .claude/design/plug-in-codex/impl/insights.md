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

## Pitfalls

- **Don't forget**: `ClaudeCliProvider::generateSettingsJson()` reads from `getAiOptions()` which returns the default provider's options in namespaced mode. This is correct for now but should be made explicit in P2 by using `getAiOptionsForProvider('claude')`.

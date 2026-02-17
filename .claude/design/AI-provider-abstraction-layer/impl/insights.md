# Implementation Insights

## Pre-implementation Notes

### Why the previous attempt failed (2026-02-17)

1. **Context overflow**: The full spec is 600 lines. Combined with reading 70+ source files and generating edits, the context window was exhausted.
2. **No checkpoints**: All changes were attempted in a single session with no intermediate commits. A crash left the app in a half-renamed state.
3. **Missing plan.md**: The workflow rules prescribe a plan.md but it wasn't created. The agent re-derived implementation steps from the raw spec each time.
4. **No implementation memory**: Without `impl/todos.md`, there was no way to resume from where the crash occurred.

### File count by layer (for context budgeting)

| Layer | Files | Estimated lines |
|-------|-------|-----------------|
| Interfaces (new) | 5 | ~150 |
| Provider (new) | 1 | ~1200 |
| Migrations (new) | 3 | ~200 |
| Models (rename) | 5 | ~700 |
| Services (rename) | 4 | ~500 |
| Controllers (rename) | 3 | ~1300 |
| Enums (rename) | 2 | ~100 |
| Views (edit) | 14 | ~5000 |
| Config (edit) | 4 | ~300 |
| Tests (rename) | 17 | ~4000 |
| **Total** | **58+** | **~13,500** |

### Decisions

- Phases 3+4 are tightly coupled (migration then model rename). Could merge into one phase IF the file count stays manageable (~10 files).
- Old service files (ClaudeCliService, ClaudeWorkspaceService) are kept alive until P11. This avoids breaking anything during intermediate phases.
- Test file renames are deferred to P10 to avoid fixing test references multiple times during intermediate phases. Tests may reference old class names until P10 — this is acceptable if we temporarily adjust the specific tests that break.

## During Implementation

### P1: Create Interfaces (2026-02-17)

- All 5 interfaces created without issues. Method signatures derived from existing `ClaudeCliService` and `ClaudeWorkspaceService`.
- `AiConfigProviderInterface::hasConfig()` uses generic key names (`hasConfigFile`, `hasConfigDir`) instead of Claude-specific names (`hasCLAUDE_MD`, `hasClaudeDir`). The concrete `ClaudeCliProvider` can add provider-specific keys as documented in spec.
- No deviations from plan.

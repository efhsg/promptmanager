# Implementation Todos

## Status: Phase 1 complete — ready to commit

## Phases
- [x] **P1**: Backend Foundation — Interface, Model, Config, RunAiJob Refactor
- [ ] **P2**: Controllers & CodexCliProvider
- [ ] **P3**: Project Form View — Per-Provider Tabs
- [ ] **P4**: Chat View Frontend — Dynamic Options, Event Router, Docker

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

## Session Log

| Date | Phase | Commit | Notes |
|------|-------|--------|-------|
| 2026-02-18 | P1 | pending | All steps complete, linter clean, tests green |

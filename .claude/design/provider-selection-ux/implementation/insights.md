# Insights: provider-selection-ux

## Decisions
- Config badge moves from after provider badge to after git branch badge (context group)
- Badge order: project → git branch → config → [divider] → lock/edit icon → provider → model → permission
- `badge-setting--locked` added as semantic marker but styling handled by parent `.claude-combined-bar--locked`
- Edit hint state tracked via `_editHintActive` boolean + `_editHintTimer`/`_pulseTimer` for cleanup
- Used `aria-disabled="true"` on settings section during lock for accessibility
- Locked alert uses `role="alert"` + `aria-live="assertive"` for screen reader announcement

## Findings
- Combined bar click: `#claude-combined-settings` has its own click listener at L834 calling `toggleSettingsExpanded()`, so adding a guard there is sufficient
- `syncCombinedBar()` needs no change — it already hides settings part when expanded
- `collapseSettings()` is only called from badge bar click (collapse from inside card) — no lock-check needed there
- `summarizeAndContinue()` calls `newSession()` — works correctly with new flow since it either auto-sends or places text in editor
- Existing `.claude-combined-bar__settings:hover .badge { opacity: 0.8 }` is overridden by locked state CSS to keep opacity at 1

## Final Result
- Linter: 0 issues
- Tests: 1133 passed, 0 errors, 0 failures (21 skipped — pre-existing)
- No backend changes required
- No open issues

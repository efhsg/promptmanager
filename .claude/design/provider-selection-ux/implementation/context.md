# Context: provider-selection-ux

## Goal
Improve the Provider Selection UX in the AI Chat view: settings visible on fresh load, locked indicator during sessions, non-clickable settings when locked, locked alert fallback, visual grouping in combined bar, improved new session flow, central lock state tracking.

## Scope
- `yii/views/ai-chat/index.php` — JS changes (lock state, init flow, combined bar, newSession)
- `yii/web/css/ai-chat.css` — CSS (locked modifier, muted badges, pulse animation, divider, alert)
- Pure frontend — no backend changes

## Key References
- Spec: `.claude/design/provider-selection-ux/spec.md`
- View: `yii/views/ai-chat/index.php`
- CSS: `yii/web/css/ai-chat.css`
- `lockProvider()` at L1037, `unlockProvider()` at L1048
- `updateSettingsSummary()` at L3036
- `syncCombinedBar()` at L3083
- `toggleSettingsExpanded()` at L3189
- `newSession()` at L2915
- `init()` at L577
- `expandSettings()` at L3028
- Combined bar HTML at L97-104
- Settings card HTML at L116-167

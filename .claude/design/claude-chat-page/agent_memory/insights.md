# Insights

- `rbac.php` uses camelCase keys (`runClaude`) which `EntityPermissionService::camelCaseToHyphen()` converts to kebab-case (`run-claude`). The new entry must use camelCase key `claude` (no conversion needed since it has no uppercase letters).
- `actionRunClaude` currently only handles `prompt` (follow-up string) vs stored model content. The `contentDelta` path is a new third option that slots between these two in priority.
- The existing modal JS (`ClaudeCliModal`) embeds `$runClaudeUrl` and `$csrfToken` directly in JS heredoc strings — new view should follow the same pattern.
- Existing test file `ScratchPadControllerTest` uses `createControllerWithClaudeService()` helper — new tests should reuse this.
- `view.php` has three separate Claude button instances (lines 70-76, 109-115, 142-148) — all must be updated.
- **marked.js v17 deviation**: The `highlight` callback in `setOptions()` was removed in marked v8. Using `marked.use({ renderer: { code(token) { ... } } })` with hljs inside the renderer achieves identical behavior. No extra dependency needed (no `marked-highlight` package).
- JS asset sizes: marked.min.js = 41KB (v17.0.1), purify.min.js = 23KB (v3.3.1). Plan estimated ~40KB and ~7KB respectively.

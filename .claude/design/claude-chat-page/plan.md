# Claude Chat Page — Implementation Plan

## Files to Create

### 1. `yii/views/scratch-pad/claude.php`
The main view file for the new Claude chat page.

**Structure:**
- Page header with back link and settings toggle button
- Breadcrumbs: Saved Scratch Pads → [Name] → Claude CLI
- Collapsible settings card (Bootstrap `collapse` component)
- Single-column stacked layout: prompt input (full width) → conversation panel (full width)
- Prompt input has two modes: Quill editor (initial) and textarea (follow-ups)
- Register QuillAsset for editor
- All JavaScript registered via `$this->registerJs()`

**PHP variable received from controller:**
- `$model` — ScratchPad instance (only variable passed from controller)

**Variables derived in view** (same pattern as `_claude-cli-modal.php`):
- `$runClaudeUrl` — `Url::to(['/scratch-pad/run-claude', 'id' => $model->id])`
- `$checkConfigUrl` — `$model->project ? Url::to(['/project/check-claude-config', 'id' => $model->project->id]) : null`
- `$projectDefaults` — `$model->project ? $model->project->getClaudeOptions() : []`
- `$csrfToken` — `Yii::$app->request->csrfToken`

**JS embedding** (mandatory — matches existing `_claude-cli-modal.php:15-16`):
- `$projectDefaults` → embed via `Json::htmlEncode($projectDefaults)` (safe for JS object literal)
- `$checkConfigUrl` → embed via `Json::htmlEncode($checkConfigUrl)` (safe for nullable string)
- `$runClaudeUrl` → embed as string literal in heredoc (safe: `Url::to()` returns URL-encoded paths)
- `$csrfToken` → embed as string literal in heredoc (alternatively read from `meta[name=csrf-token]`)

**JavaScript object: `window.ClaudeChat`**
- Manages session state (sessionId, messages array, lastSentDelta, inputMode)
- Tracks `inputMode`: `'quill'` (initial) or `'textarea'` (after first send)
- Handles config prefilling and status checking
- Sends based on active input mode:
  - Quill mode: sends `contentDelta` (stringified Delta JSON) — server converts to markdown
  - Textarea mode: sends `prompt` (plain text string) — server uses as-is
- Posts to `/scratch-pad/run-claude` endpoint with `X-CSRF-Token` header on all POST requests (Yii2 rejects POSTs without valid CSRF token)
- Renders conversation messages with `marked.js` + `highlight.js` (via `highlight` callback — see spec § Markdown Rendering), sanitized through `DOMPurify.sanitize(html, { ADD_ATTR: ['class', 'target', 'rel'] })` before DOM insertion
- Uses server-returned `promptMarkdown` as the sole source for "You" messages in the conversation panel
- Shows a loading placeholder bubble in the conversation panel while waiting for response (replaced by real response or error on completion)
- Updates sticky settings summary bar when settings card collapses (reads current dropdown values)
- Stores last-sent Quill Delta; "Reuse last prompt" re-shows Quill editor with that Delta
- Manages input mode switching: `switchToTextarea()` hides Quill / shows textarea; `switchToQuill()` hides textarea / shows Quill editor
- Auto-scrolls conversation panel with scroll-position preservation: tracks `isUserScrolledUp` via scroll event (true when `scrollTop + clientHeight < scrollHeight - threshold`); `addMessage()` skips auto-scroll when user has scrolled up; auto-scroll resumes when user scrolls back to bottom
- Handles loading states, errors, new session

### 2. `yii/web/js/marked.min.js`
Minified `marked.js` library for client-side markdown rendering.
- Download from marked.js GitHub releases (pin to a specific version, e.g. v15.x, ~40KB minified)
- No dependencies; integrates with highlight.js for code block highlighting

### 2b. `yii/web/js/purify.min.js`
DOMPurify library for HTML sanitization of marked.js output.
- Download from DOMPurify GitHub releases (pin to a specific version, ~7KB minified)
- All `marked()` output must pass through `DOMPurify.sanitize()` before DOM insertion

### 3. `yii/web/css/claude-chat.css`
Scoped CSS for the Claude chat page.

**Styles:**
- `.claude-settings-summary` — sticky summary bar below collapsed settings card (small muted text, clickable to expand)
- `.claude-prompt-section` — full-width prompt input area
- `.claude-prompt-section .ql-container` — Quill editor min-height (300px initial)
- `.claude-followup-textarea` — follow-up textarea styling (full width, 3 rows default)
- `.claude-conversation` — scrollable container, max-height, border
- `.claude-message` — individual message card
- `.claude-message--user` — user message styling (light background)
- `.claude-message--claude` — Claude response styling (left border accent)
- `.claude-message--loading` — placeholder bubble with animated dots
- `.claude-message__header` — message header with role icon
- `.claude-message__body` — rendered content area
- `.claude-message__meta` — metadata footer (duration, tokens)
- `.claude-conversation__empty` — empty state placeholder
- `.claude-thinking-dots` — CSS-only three-dot animation for loading placeholder
- Markdown content styles within messages (code blocks, tables, etc.)

### Asset Registration

All three new assets are registered directly in `claude.php` via Yii view methods (no new AssetBundle class needed — matches the lightweight pattern used elsewhere):

```php
QuillAsset::register($this);  // Quill + highlight.js (existing); needed for initial prompt editor
$this->registerJsFile('@web/js/marked.min.js', ['position' => View::POS_HEAD]);
$this->registerJsFile('@web/js/purify.min.js', ['position' => View::POS_HEAD]);
$this->registerCssFile('@web/css/claude-chat.css');
```

Note: QuillAsset is always loaded because the initial prompt uses the Quill editor. After first send, the Quill editor DOM is hidden (not destroyed) so no re-initialization is needed when "Reuse last prompt" or "Switch to rich editor" re-shows it.

---

## Files to Modify

### 4. `yii/controllers/ScratchPadController.php`
Add new `actionClaude` method.

```php
public function actionClaude(int $id): string
{
    $model = $this->findModel($id);

    if ($model->project_id === null) {
        throw new NotFoundHttpException('Claude CLI requires a project.');
    }

    return $this->render('claude', [
        'model' => $model,
    ]);
}
```

**Also update `behaviors()`:**
- No direct change needed in `behaviors()` — the access rule at `ScratchPadController.php:82` uses `array_keys($this->actionPermissionMap)`, which is loaded from RBAC config. Adding `'claude' => 'viewScratchPad'` to `rbac.php` is sufficient.

### 4b. `yii/services/EntityPermissionService.php`
Add `'claude'` to the `MODEL_BASED_ACTIONS` constant.

**Before:**
```php
private const MODEL_BASED_ACTIONS = ['view', 'update', 'delete', 'renumber', 'run-claude'];
```

**After:**
```php
private const MODEL_BASED_ACTIONS = ['view', 'update', 'delete', 'renumber', 'run-claude', 'claude'];
```

**Why this is critical:** The access control callback at `ScratchPadController.php:84` calls `$this->permissionService->isModelBasedAction($action->id)`. If `'claude'` is missing from this list, the `findModel()` callback won't execute, RBAC ownership checking will be skipped, and any authenticated user could access any scratch pad's Claude page.

### 5. `yii/views/scratch-pad/view.php`
Change the Claude button from a modal trigger to a page link.

**There are three separate Claude buttons** in the view (Content accordion header at ~line 71, Response accordion header at ~line 110, and single-content card header at ~line 143). All three must be updated.

**Before** (each button):
```php
<button type="button" class="btn btn-primary btn-sm text-nowrap<?= !$canRunClaude ? ' disabled' : '' ?>"
        <?= !$canRunClaude ? 'disabled' : '' ?>
        <?= $claudeTooltip ? 'title="' . Html::encode($claudeTooltip) . '" ...' : '' ?>
        onclick="window.ClaudeCliModal.show()">
    <i class="bi bi-terminal-fill"></i> Claude
</button>
```

**After** (each button):
```php
<?= Html::a('<i class="bi bi-terminal-fill"></i> Claude',
    $canRunClaude ? ['claude', 'id' => $model->id] : '#',
    [
        'class' => 'btn btn-primary btn-sm text-nowrap' . (!$canRunClaude ? ' disabled' : ''),
        'title' => $claudeTooltip ?: null,
        'data-bs-toggle' => $claudeTooltip ? 'tooltip' : null,
    ]) ?>
```

**Also remove:**
- The `_claude-cli-modal.php` render call at line 171
- **Delete `_claude-cli-modal.php`** — it becomes dead code; tracked by git so recoverable

### 6. `yii/config/rbac.php`
Add `'claude'` action mapping to the scratchPad permissions.

```php
'scratchPad' => [
    // ... existing entries ...
    'claude' => 'viewScratchPad',  // Same permission as view
],
```

### 7. `yii/web/css/site.css`
No changes needed — scoped styles go in `claude-chat.css`.

---

## Implementation Steps

### Step 1: Add JS assets
- Download `marked.min.js` to `yii/web/js/` — check current stable version at https://github.com/markedjs/marked/releases before downloading; pin to exact version (e.g. `marked@15.0.4`); verify no breaking API changes in `marked.parse()` and `highlight` callback
- Download `purify.min.js` (DOMPurify) to `yii/web/js/` — check current stable version at https://github.com/cure53/DOMPurify/releases; pin to exact version
- Verify marked.js works with the existing highlight.js version in `yii/web/quill/1.3.7/highlight/`

### Step 2: Create CSS file
- Create `yii/web/css/claude-chat.css` with conversation panel styles
- Keep styles scoped to `.claude-chat-page` wrapper class

### Step 3: Add controller action, RBAC, model-based action registration, and update `actionRunClaude`
- Add `actionClaude(int $id)` to `ScratchPadController`
- Add `'claude'` to RBAC action permission map in `config/rbac.php`
- **Add `'claude'` to `EntityPermissionService::MODEL_BASED_ACTIONS`** — without this, ownership checking is bypassed (see § Files to Modify → 4b)
- No new RBAC rule needed — reuses `viewScratchPad` / `ScratchPadOwnerRule`
- Modify `actionRunClaude` to accept `contentDelta` parameter and return `promptMarkdown` (see "What Changes in Existing Code" section)

### Step 4: Create the view file
- Build `views/scratch-pad/claude.php` with the three-section stacked layout
- Settings card with collapse
- Prompt section containing both:
  - Quill editor (visible initially) with scratch pad content pre-loaded via `quill.setContents(JSON.parse(<?= Json::htmlEncode($model->content ?? '{"ops":[]}') ?>))`
  - Textarea (hidden initially, `d-none`) for follow-up prompts: `<textarea class="form-control claude-followup-textarea" rows="3" placeholder="Ask a follow-up question..."></textarea>`
  - "Switch to rich editor" link (hidden initially, `d-none`)
- Conversation panel with empty state (full width)
- **Button initial visibility**: "Reuse last prompt" and "New Session" buttons rendered with `d-none` class in the HTML; `send()` removes `d-none` after first successful run; `newSession()` re-adds `d-none`
- JavaScript for `ClaudeChat` object:
  - `init()` — setup, prefill config from `Json::htmlEncode`d defaults, check config status; set `inputMode = 'quill'`; configure marked with `{ breaks: true, gfm: true, headerIds: false, mangle: false }`, a `highlight` callback delegating to hljs, and a custom `renderer.link` that adds `target="_blank" rel="noopener noreferrer"` to all links
  - `send()` — if `inputMode === 'quill'`: store current Quill Delta as `lastSentDelta`, serialize and POST `contentDelta`; if `inputMode === 'textarea'`: POST `prompt` (plain text); both: show loading placeholder in conversation, add "You" message from server `promptMarkdown`, render Claude response (replacing placeholder); on first successful run: call `collapseSettings()`, call `switchToTextarea()`, show Reuse/New Session buttons (remove `d-none`)
  - `switchToTextarea()` — hide Quill editor container, show textarea and "Switch to rich editor" link, set `inputMode = 'textarea'`
  - `switchToQuill(loadDelta)` — hide textarea and "Switch to rich editor" link, show Quill editor container, optionally load Delta into editor, set `inputMode = 'quill'`
  - `addMessage(role, content, meta)` — render markdown via `marked()`, sanitize via `DOMPurify.sanitize(html, { ADD_ATTR: ['class', 'target', 'rel'] })`, insert into conversation panel
  - `showLoadingPlaceholder()` — append a Claude-styled placeholder bubble with CSS animated dots; returns reference for later replacement
  - `removeLoadingPlaceholder()` — remove placeholder, insert real response or error in its place
  - `renderMarkdown(text)` — `DOMPurify.sanitize(marked.parse(text), ...)`
  - `reuseLastPrompt()` — call `switchToQuill(lastSentDelta)` to show Quill editor with previously sent Delta
  - `newSession()` — clear `sessionId`, `messages` array, and `lastSentDelta`; clear conversation panel DOM (restore empty-state placeholder); call `expandSettings()`; hide Reuse and New Session buttons (add `d-none`); call `switchToQuill(initialDelta)` to restore Quill editor with original scratch pad content
  - `copyConversation()` — copy all messages as plain text
  - `collapseSettings()` — collapse card, read current dropdown values, update summary bar text
  - `expandSettings()` — expand card, hide summary bar

### Step 5: Update the view page and remove modal
- Change all three Claude buttons to links in `views/scratch-pad/view.php` (Content header ~line 71, Response header ~line 110, single-content header ~line 143)
- Remove `_claude-cli-modal.php` render call at line 171
- Delete `yii/views/scratch-pad/_claude-cli-modal.php` (dead code; tracked by git)

### Step 6: Automated tests
Add Codeception unit tests per `.claude/rules/testing.md` ("Add/adjust the smallest relevant Codeception test when it meaningfully reduces regression risk").

Add all new tests to the existing **`tests/unit/controllers/ScratchPadControllerTest.php`** (already has 10+ tests including `actionRunClaude` coverage):

**`actionClaude` tests:**
- `testClaudeActionRejectsNullProject` — scratch pad without `project_id` throws `NotFoundHttpException`
- `testClaudeActionRendersViewForOwner` — valid owner with project gets 200

**`actionRunClaude` `contentDelta` tests:**
- `testRunClaudeConvertsContentDeltaToMarkdown` — sending `contentDelta` field triggers conversion and returns `promptMarkdown`
- `testRunClaudeReturnsPromptMarkdownInResponse` — response includes `promptMarkdown` field
- `testRunClaudeBackwardCompatibleWithPromptField` — existing `prompt` field still works (existing tests already cover this, verify they still pass)

### Step 7: Manual verification
- Verify page loads with scratch pad content in Quill editor (full width)
- Verify config status check works
- Verify sending prompt shows loading placeholder in conversation panel (animated dots)
- Verify loading placeholder is replaced by real Claude response on success
- Verify loading placeholder is replaced by error alert on failure
- Verify after first send: Quill editor hides, textarea appears for follow-ups
- Verify follow-up prompts from textarea work with session continuity (sent as `prompt`, not `contentDelta`)
- Verify "Switch to rich editor" shows Quill editor, hides textarea
- Verify follow-up from Quill editor (via switch) sends as `contentDelta`
- Verify "Reuse last prompt" shows Quill editor with previously sent Delta
- Verify settings summary bar appears with correct model/permission/config when settings collapse
- Verify clicking summary bar re-expands settings card
- Verify new session resets correctly (clears conversation, shows Quill with scratch pad content, hides reuse/new session buttons)
- Verify stacked layout renders correctly at all viewport widths (no breakpoint issues)
- Verify markdown rendering with code blocks, lists, headings
- Verify DOMPurify strips unexpected HTML from rendered markdown
- Verify copy conversation works

---

## Dependencies

- **marked.js** — new JS library, local asset (`yii/web/js/marked.min.js`), pinned version
- **DOMPurify** — new JS library, local asset (`yii/web/js/purify.min.js`), pinned version, mandatory for sanitizing markdown HTML output
- **highlight.js** — already loaded via QuillAsset (`yii/web/quill/1.3.7/highlight/highlight.min.js`)
- **Quill** — already loaded via QuillAsset
- **Bootstrap 5** — already loaded (collapse, cards, grid)
- **Bootstrap Icons** — already loaded (bi-terminal-fill, bi-send-fill, etc.)

No Composer or npm dependency changes required. New JS libraries are downloaded as static files.

---

## What Does NOT Change

- `ClaudeCliService` — no modifications
- `ClaudeWorkspaceService` — no modifications
- `ScratchPad` model — no modifications
- `actionConvertFormat` controller action — unchanged (used for copy-to-clipboard, not for this feature)
- API controller — no modifications
- Database schema — no migrations needed
- RBAC rules — no new rules (reuses `ScratchPadOwnerRule`)

## What Changes in Existing Code

| File | Change |
|------|--------|
| `ScratchPadController.php` | Add `actionClaude()`; update `actionRunClaude()` for `contentDelta` + `promptMarkdown` |
| `EntityPermissionService.php` | Add `'claude'` to `MODEL_BASED_ACTIONS` constant |
| `config/rbac.php` | Add `'claude' => 'viewScratchPad'` to actionPermissionMap |
| `views/scratch-pad/view.php` | Replace 3 button triggers with links; remove modal render |
| `views/scratch-pad/_claude-cli-modal.php` | **Delete** (dead code) |

### `actionRunClaude` — accept `contentDelta` parameter

Add support for a `contentDelta` field in the request body. When present, convert it to markdown server-side using the existing `ClaudeCliService::convertToMarkdown()`. This replaces the current behavior where the modal always sent the scratch pad's stored content.

```php
// Existing: custom prompt (follow-up plain text) vs scratch pad content
$customPrompt = $requestOptions['prompt'] ?? null;
$contentDelta = $requestOptions['contentDelta'] ?? null;
$sessionId = $requestOptions['sessionId'] ?? null;

// Defensive: re-encode contentDelta to a JSON string if it arrived as an array.
// JS sends JSON.stringify(quill.getContents()) which produces a string value in the
// JSON body. But if a caller omits stringify, json_decode produces an array instead.
// convertToMarkdown() requires a string parameter.
if (is_array($contentDelta)) {
    $contentDelta = json_encode($contentDelta);
}

if ($customPrompt !== null) {
    $markdown = $customPrompt;
} elseif ($contentDelta !== null) {
    $markdown = $this->claudeCliService->convertToMarkdown($contentDelta);
} else {
    $markdown = $this->claudeCliService->convertToMarkdown($model->content ?? '');
}
```

This is backward-compatible: existing callers that send `prompt` (plain text follow-ups) or no prompt (use stored content) continue to work. The new `contentDelta` path lets the chat page send edited Quill Delta JSON directly.

Also return the converted markdown in the response so the conversation panel can display the "You" message:

```php
return [
    // ... existing fields ...
    'promptMarkdown' => $markdown,  // echo back for conversation display
];
```

---

## Risks & Considerations

1. **marked.js version**: Pin to a specific version to avoid breaking changes. Download the minified file rather than using a CDN (offline-capable).

2. **XSS via markdown**: All `marked()` output is sanitized through DOMPurify before DOM insertion. See spec § Markdown Rendering → Sanitization for configuration details. This is mandatory per `.claude/rules/security.md` ("Sanitize output with `Html::encode()` in views"). The threat model is low-risk (single-user, owner-scoped data, CLI-generated output) but DOMPurify provides defense-in-depth.

3. **Large conversations**: If a conversation grows very long, the panel could become slow. Reasonable for the expected use case (5-20 exchanges). No pagination needed.

4. **Modal cleanup**: Delete `_claude-cli-modal.php` after removing the render call from `view.php`. The file is tracked by git and recoverable if needed.

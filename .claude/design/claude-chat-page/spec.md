# Claude Chat Page — Functional Specification

## Overview

Replace the Claude CLI modal dialog on the scratch pad view page with a dedicated full page that provides a better UX for composing prompts, configuring Claude CLI options, and reviewing conversation history.

**Route**: `GET /scratch-pad/claude?id={scratchPadId}`
**Entry point**: Claude button on scratch pad view page links here instead of opening a modal.

---

## Current Behavior (Modal)

1. User views a scratch pad at `/scratch-pad/view?id=X`
2. Clicks the "Claude" button → modal opens
3. Modal step 1: Configure CLI options (model, permissions, tools, system prompt)
4. Modal step 2: Runs Claude with the scratch pad content as prompt (converted from Quill Delta to markdown)
5. Output shown as raw preformatted text in dark container
6. User can send follow-up prompts via a text input
7. Closing the modal loses the session

**Problems:**
- Cannot see scratch pad content while viewing Claude output
- Cannot edit the prompt before sending
- Follow-up input is a single-line text field (no rich formatting)
- Limited screen real estate in modal
- Conversation accumulates as raw text without structure

---

## New Behavior (Full Page)

### Page Layout

The page has three distinct sections in a single-column stacked layout:

```
┌──────────────────────────────────────────────────────────────┐
│  ← Back to "Scratch Pad Name"             Claude CLI        │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─ CLI Settings (collapsible card) ──────────────────────┐  │
│  │ Model | Permission Mode | Allowed Tools | Disallowed   │  │
│  │ Append to System Prompt | Config status indicator      │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌─ Prompt ──────────────────────────────────────────────┐   │
│  │  ┌─ Quill Editor (initial) / Textarea (follow-up) ─┐ │   │
│  │  │ (pre-filled with scratch pad content)            │ │   │
│  │  │                                                  │ │   │
│  │  └──────────────────────────────────────────────────┘ │   │
│  │  [▶ Send] [↻ Reuse] [⟳ New Session]                  │   │
│  └───────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─ Conversation ────────────────────────────────────────┐   │
│  │                                                       │   │
│  │  ┌─ You ───────────────────────────────────────────┐  │   │
│  │  │ Rendered prompt                                 │  │   │
│  │  └─────────────────────────────────────────────────┘  │   │
│  │                                                       │   │
│  │  ┌─ Claude ────────────────────────────────────────┐  │   │
│  │  │ Formatted markdown with code highlighting       │  │   │
│  │  │                                                 │  │   │
│  │  │ 12.3s · 5.2k/1.1k · opus · project config      │  │   │
│  │  └─────────────────────────────────────────────────┘  │   │
│  │                                                       │   │
│  │  [📋 Copy All]                                        │   │
│  └───────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

### Section 1: CLI Settings (Top)

A collapsible card (Bootstrap collapse) containing the configuration form.

**Fields** (same as current modal):
- Model dropdown (Sonnet, Opus, Haiku, Use default)
- Permission Mode dropdown
- Allowed Tools text input
- Disallowed Tools text input
- Append to System Prompt textarea

**Behavior:**
- Starts **expanded** on page load
- Config status indicator checks project Claude config via AJAX (reuses existing `/project/check-claude-config` endpoint)
- Values pre-filled from project defaults (same as current modal)
- **Collapses automatically** after the first successful run
- Toggle button in the page header allows re-expanding at any time
- Compact two-column grid layout (same as current modal form)

**Sticky summary bar** (visible only when card is collapsed):
- Shown directly below the collapsed card header as a small muted `<div>`
- Displays: active model, permission mode, and config source — e.g., "Opus · Plan · Project config"
- Values read from the form dropdowns at collapse time (not from server)
- Clicking the summary bar expands the settings card

### Section 2: Prompt Editor (Full Width, Stacked)

The prompt input area uses two different input modes: a Quill editor for the initial prompt, and a plain textarea for follow-ups.

**Initial state (Quill editor):**
- Pre-filled with the scratch pad's content (Quill Delta loaded directly)
- Full Quill toolbar (bold, italic, code, lists, headers, etc.)
- Editor container with min-height 300px
- Full width (no column constraint)

**After first send (textarea mode):**
- Quill editor is **hidden** (not destroyed — preserved for "Reuse last prompt")
- A plain `<textarea>` is shown instead (3 rows, auto-expandable, placeholder: "Ask a follow-up question...")
- Last-sent Quill Delta is stored in a JS variable for the Reuse button
- A **Switch to rich editor** link below the textarea re-shows the Quill editor (empty) and hides the textarea, for the rare follow-up that needs formatting

**Why textarea for follow-ups:** Follow-ups are typically short conversational messages ("explain that further", "now add error handling"). The full Quill toolbar is unnecessary overhead. The Quill instance is preserved hidden so "Reuse last prompt" can reload it instantly without re-initialization.

**Action buttons below input:**
- **Send** (primary) — sends the active input (Quill Delta via `contentDelta` or textarea text via `prompt`) to Claude
- **Reuse last prompt** (outline, hidden until first send) — hides textarea, shows Quill editor, loads `lastSentDelta` into it
- **New Session** (outline, hidden until first run) — resets session, clears conversation, restores initial Quill editor with scratch pad content
- **Switch to rich editor** (text link, hidden until textarea mode) — shows empty Quill editor, hides textarea

**Loading state:**
- Disable the Send button during execution
- Loading feedback appears in the **conversation panel** (not below the editor) — see Section 3

### Section 3: Conversation Panel (Full Width, Stacked Below Prompt)

A scrollable panel showing the full conversation with visual distinction between user messages and Claude responses.

**Empty state:**
- Shows a centered message: "Send a prompt to start a conversation"
- Light gray text, terminal icon

**Message rendering:**
Each message is displayed in a card-like container:

**User messages:**
- Light background (white/light gray)
- Header: "You" with a user icon, right-aligned
- Content: prompt text (markdown-rendered from Quill Delta conversion)
- Compact styling

**Claude responses:**
- Slightly different background (very light blue or white with left border accent)
- Header: "Claude" with terminal icon
- Content: **formatted markdown** — headings, code blocks with syntax highlighting, lists, bold, etc.
- Metadata footer: duration, token counts (in/out), model name, config source
- All metadata on one line, small muted text

**Loading state** (shown while waiting for Claude CLI response):
- A placeholder message appears at the bottom of the conversation panel styled as a Claude message
- Header: "Claude" with terminal icon (same as a real response)
- Body: animated three-dot "thinking" indicator (CSS animation, no JS timer)
- Text below dots: "Running Claude CLI..." in small muted text
- Placeholder is removed and replaced by the real response when it arrives, or replaced by an inline error alert on failure
- Panel auto-scrolls to show the placeholder

**Scrolling:**
- Panel has a fixed max-height with vertical scroll
- Auto-scrolls to bottom when new messages appear
- Scroll position preserved when user scrolls up

**Actions:**
- **Copy All** button at the bottom — copies the full conversation as plain text

---

## Data Flow

### Initial Page Load
1. Controller loads scratch pad model (with ownership check), passes `$model` to view
2. View derives all needed data from the model (URLs via `Url::to()`, project defaults via `$model->project->getClaudeOptions()`) — same pattern as existing `_claude-cli-modal.php`
3. View renders Quill editor with scratch pad content pre-loaded
4. Config status checked via AJAX (same as current)

### Sending a Prompt (First Run)
1. User clicks Send
2. JS serializes Quill editor content as a **JSON string** via `JSON.stringify(quill.getContents())` — this produces a string value (e.g., `'{"ops":[...]}'`), not a nested object
3. JS posts the stringified Delta as `contentDelta` (a string field in the POST JSON body) to `/scratch-pad/run-claude?id=X` with options and `X-CSRF-Token` header (Yii2 rejects POSTs without a valid CSRF token) — the controller converts Delta to markdown server-side via `ClaudeCliService::convertToMarkdown()` (canonical conversion path for Claude prompt preparation). As a defensive measure, the controller re-encodes `contentDelta` to a JSON string if it arrives as an array (see plan § What Changes in Existing Code).
4. On success:
   - User's prompt added to conversation panel as "You" message, rendered from the server-returned `promptMarkdown` field (guarantees the panel shows exactly what Claude received)
   - Claude's response (markdown) rendered and added as "Claude" message
   - Session ID stored for follow-ups
   - Settings card collapses
   - Quill editor hidden; textarea shown for follow-ups
   - Conversation auto-scrolls to bottom
5. On error: loading placeholder in conversation panel replaced by an inline error alert (styled as a Claude message with `alert-danger` body) — errors always appear in the conversation panel, never below the editor, so the user sees them in the natural reading flow

### Sending Follow-up Prompts
1. User writes follow-up in the textarea (or Quill editor if switched via "Switch to rich editor")
2. Clicks Send
3. JS sends based on active input:
   - **Textarea**: posts `prompt` (plain text string) to `/scratch-pad/run-claude?id=X` with session ID — same path the existing modal uses for follow-ups
   - **Quill editor** (if user switched to rich editor): posts `contentDelta` (stringified Delta JSON) — server-side conversion path
4. Both the follow-up prompt and response added to conversation panel
5. Input clears; textarea remains visible for next follow-up

### New Session
1. User clicks "New Session"
2. Session ID cleared
3. Conversation panel cleared
4. Settings card re-expands
5. Textarea hidden; Quill editor re-shown with scratch pad content reloaded

---

## Markdown Rendering

Claude's output is markdown text. It needs to be rendered as formatted HTML with:
- Headings (h1-h6)
- Code blocks with syntax highlighting (using highlight.js, already loaded via QuillAsset)
- Inline code
- Bold, italic
- Lists (ordered, unordered)
- Blockquotes
- Links (open in new tab with `rel="noopener noreferrer"`)
- Tables

**Implementation**: Use `marked.js` library (lightweight, no dependencies, integrates with highlight.js). Include as a local asset file registered in the view via `$this->registerJsFile()`.

**highlight.js integration** (required for code block syntax highlighting):
- Configure marked with a `highlight` callback that delegates to the highlight.js instance already loaded via QuillAsset (`yii/web/quill/1.3.7/highlight/highlight.min.js`):
  ```js
  marked.setOptions({
      highlight: function(code, lang) {
          if (lang && hljs.getLanguage(lang))
              return hljs.highlight(code, { language: lang }).value;
          return hljs.highlightAuto(code).value;
      }
  });
  ```
- This adds `class="hljs"` and token `<span class="hljs-*">` elements to code blocks, which are styled by the existing `highlight/default.min.css` already loaded via QuillAsset.

**Sanitization** (mandatory per `.claude/rules/security.md` — "Sanitize output with `Html::encode()` in views"):
- Configure marked with `{ breaks: true, gfm: true, headerIds: false, mangle: false }`
- Use a custom `renderer` to force all links to open in a new tab with `target="_blank" rel="noopener noreferrer"`
- Include **DOMPurify** (`yii/web/js/purify.min.js`, ~7KB) as a mandatory post-processing step: all `marked()` output is passed through `DOMPurify.sanitize()` before insertion into the DOM
- **DOMPurify must preserve highlight.js classes**: configure with `DOMPurify.sanitize(html, { ADD_ATTR: ['class', 'target', 'rel'] })` so that `class="hljs-*"` on `<span>` elements and `target="_blank"` on links survive sanitization
- Insert sanitized HTML via `innerHTML` only — never via `document.write` or `eval`

**Threat model**: This is a single-user, owner-scoped application. Both the user prompts (composed in Quill) and Claude responses (generated by our own CLI) are trusted sources. DOMPurify is defense-in-depth against unexpected content, not a primary security boundary.

User prompts are displayed in the conversation panel using `promptMarkdown` returned by the server. For Quill-composed prompts (initial send, rich editor follow-ups), the server converts Delta to markdown. For textarea follow-ups, the server echoes back the plain text as-is. Both are rendered with the same markdown renderer for consistency.

### Conversion Path

Two server-side Delta-to-markdown paths exist in the codebase:
- `ClaudeCliService::convertToMarkdown()` — used by `actionRunClaude` for Claude prompt preparation
- `CopyFormatConverter::convertFromQuillDelta()` with `CopyType::MD` — used by `actionConvertFormat` for copy-to-clipboard

**Canonical path for this feature**: `ClaudeCliService::convertToMarkdown()` via `actionRunClaude`. The view sends Quill Delta JSON directly; the controller converts it server-side. This avoids an extra AJAX roundtrip and uses the same path the existing modal already relies on.

---

## Access Control

- Same permission as current `run-claude` action: requires `viewScratchPad` permission (owner check)
- The `claude` action reuses the existing RBAC configuration — maps to `viewScratchPad`
- Only available when scratch pad has a `project_id` (same as current: Claude button disabled for global scratch pads)

---

## Navigation

**From scratch pad view page:**
- The Claude button changes from `onclick="window.ClaudeCliModal.show()"` to a regular link: `<a href="/scratch-pad/claude?id=X">`
- The `_claude-cli-modal.php` partial is no longer rendered on the view page

**From Claude page:**
- "Back" link in the page header navigates to `/scratch-pad/view?id=X`
- Breadcrumbs: Saved Scratch Pads → [Scratch Pad Name] → Claude CLI

---

## Responsive Behavior

Single-column stacked layout works identically on all screen sizes. No breakpoint-dependent layout switching needed.

- Settings card, prompt input, and conversation panel are all full width
- Conversation panel gets min-height for usability
- On smaller screens, the input area and conversation naturally take the available width

---

## Session Lifecycle

Session state is **intentionally ephemeral** — stored only in JavaScript variables (`sessionId`, messages array). Navigating away or reloading the page loses the session. This matches the existing modal behavior where closing resets all state, and is consistent with Claude CLI session semantics (sessions are lightweight, meant for short multi-turn exchanges).

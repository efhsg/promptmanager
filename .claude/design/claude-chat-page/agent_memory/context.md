# Context

## GOAL

Replace the Claude CLI modal dialog on the scratch pad view page with a dedicated full page (`/scratch-pad/claude?id=X`) that provides a stacked layout with collapsible CLI settings, a dual-mode prompt editor (Quill for initial, textarea for follow-ups), and a structured conversation panel with markdown rendering and DOMPurify sanitization.

## Scope Boundaries

**In scope:**
- New view file `claude.php` with three-section stacked layout
- New JS assets: `marked.min.js`, `purify.min.js`
- New CSS: `claude-chat.css`
- New controller action `actionClaude`
- RBAC entry + `EntityPermissionService` MODEL_BASED_ACTIONS update
- Update `actionRunClaude` to accept `contentDelta` and return `promptMarkdown`
- Update `view.php` buttons from modal triggers to page links
- Remove `_claude-cli-modal.php` render call and delete the file
- Unit tests for `actionClaude` and `actionRunClaude` changes

**Out of scope:**
- No changes to `ClaudeCliService`, `ClaudeWorkspaceService`, `ScratchPad` model
- No database migrations
- No new RBAC rules (reuses `viewScratchPad` / `ScratchPadOwnerRule`)
- No Composer or npm dependency changes
- No `actionConvertFormat` changes

## Non-negotiable Constraints

1. **Security**: DOMPurify sanitization mandatory on all `marked()` output before DOM insertion (spec § Markdown Rendering → Sanitization). CSRF token on all POST requests. RBAC ownership enforcement via `EntityPermissionService.MODEL_BASED_ACTIONS`.
2. **Backward compatibility**: Existing `prompt` field callers to `actionRunClaude` must continue working.
3. **No new AssetBundle**: Register JS/CSS directly in view via Yii view methods.
4. **Canonical conversion path**: Use `ClaudeCliService::convertToMarkdown()` for Delta-to-markdown, not `CopyFormatConverter`.
5. **Project rules**: PSR-12, no `declare(strict_types=1)`, no FQCNs in method bodies, follow existing patterns.

## Definition of Done

- [ ] `/scratch-pad/claude?id=X` renders correctly with settings, prompt editor, conversation panel
- [ ] Sending prompts (initial Quill Delta + follow-up textarea) works end-to-end
- [ ] Markdown rendering with syntax-highlighted code blocks works
- [ ] DOMPurify sanitization active on all rendered markdown
- [ ] Settings card collapses after first run with sticky summary bar
- [ ] Input mode switching (Quill ↔ textarea) works
- [ ] Reuse last prompt and New Session work
- [ ] Claude buttons on view.php link to new page instead of modal
- [ ] Modal file deleted
- [ ] RBAC ownership check enforced for `claude` action
- [ ] Unit tests pass
- [ ] Linter passes

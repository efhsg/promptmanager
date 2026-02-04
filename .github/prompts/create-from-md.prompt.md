# Implement Feature: "Create from MD" (PromptManager)

## 1) Restate Goal (A)
Implement a new main menu button after “Generate” named “Create from MD” that lets a user upload a Markdown file and opens a new Quill editor page with the converted content ready to edit.

## 2) Assumptions (C)
- Assumption: “Create from MD” creates a new **Prompt Template** (not a Prompt Instance), because the existing “Import from MD” flow already converts Markdown → Quill Delta and loads the Template create/edit Quill editor.
- Assumption: Reusing the current “Import from MD” implementation is preferred over introducing new parsing/conversion logic.

## 3) Constraints
- Input facts (A): The repo already has a working “Import from MD” flow for templates using `yii/controllers/PromptTemplateController.php` (`actionImportMarkdown()`), `yii/views/prompt-template/_import-modal.php` (file upload modal + JS), and `yii/views/prompt-template/_form.php` (loads imported data into Quill for editing).
- Inferences (B): The smallest correct implementation is to surface the existing template-import modal from the navbar and avoid duplicating modal/JS/endpoint logic.
- Must: Follow `RULES.md` and `.claude/CLAUDE.md` (minimal change, no unrelated refactors, no new dependencies, PHP 8.2/Yii2 conventions).
- Never: Invent new endpoints/parsers/converters when the existing “Import from MD” flow already satisfies the feature.
- Scope: Only add the navbar entry + reuse/relocate the existing import modal so it can be triggered from the main menu; keep existing template import working.
- Tone: Direct, concise, implementation-first.
- Length: Prefer small diffs; only touch files required.
- Tools/Data allowed: Repo-local files and local test/build commands only.
- Access policy: No network access; do not add dependencies.
- Output format: In the agent’s response, list touched files and any tests run (or why not).

## 4) Risk Assessment
- Ambiguity:
  - The word “prompt” could mean Template vs Instance; mitigate by explicitly implementing Template import (per Assumptions) and reusing the existing template import flow.
- Hallucinations:
  - Don’t invent new “Import from MD” patterns; require the agent to inspect and reuse the existing implementation (`_import-modal.php`, `actionImportMarkdown()`, `_form.php`).
- Scope creep:
  - Do not extend markdown support, change parsing rules, or redesign the editor; strictly add navbar entry + wiring.
- Tool misuse:
  - Avoid breaking the modal’s endpoint routing when rendering outside its original controller; require absolute route usage for the import endpoint.
- State loss / context limits:
  - Require the agent to re-check the exact localStorage key and redirect path used by the existing import flow before changing anything.

## 5) Output (copy-paste-ready prompt for Claude Opus 4.5)
```text
You are Claude Opus 4.5 acting as a coding agent inside the PromptManager repo (Yii2 + Bootstrap 5 + Quill). Implement a new main navbar button after “Generate” named “Create from MD” that lets a user upload a Markdown file and then edit the converted content in a Quill editor.

Reuse the existing “Import from MD” template workflow instead of building anything new:
- Backend conversion: `yii/controllers/PromptTemplateController.php` `actionImportMarkdown()` (uses `app\models\MarkdownImportForm`, `app\services\copyformat\MarkdownParser`, `app\services\copyformat\QuillDeltaWriter`; returns JSON with `redirectUrl` + `importData`).
- Upload UI + JS: `yii/views/prompt-template/_import-modal.php` (file upload widget; posts to import endpoint; stores payload in `localStorage` under the existing key; redirects).
- Quill editor load: `yii/views/prompt-template/_form.php` (reads the same `localStorage` key and loads the delta into Quill for editing).

Requirements
1) Add a new top navbar item after “Generate” with label “Create from MD”.
2) Clicking it should open the existing markdown import modal (file upload widget).
3) The modal must still validate/read the file server-side and convert Markdown → native Quill Delta.
4) After import, navigate to a page with a Quill editor loaded with the converted content (reuse the existing Template create page behavior).
5) Ensure there is only one `#importMarkdownModal` in the DOM on any page (avoid duplicates) and ensure the existing Templates page “Import from MD” button still works.

Implementation steps (do in order; keep diffs minimal)
- Read `RULES.md` and follow it.
- Update the main navbar in `yii/views/layouts/main.php`:
  - Insert a new item after the existing “Generate” item labeled “Create from MD”.
  - Make it visible only for authenticated users (so it doesn’t point to a missing modal for guests).
  - Make it open the modal via Bootstrap data attributes (`data-bs-toggle="modal"`, `data-bs-target="#importMarkdownModal"`).
- Render the existing modal partial globally (once) from the layout for authenticated users:
  - Reuse `yii/views/prompt-template/_import-modal.php` (don’t duplicate JS).
  - Pass it the same data it expects (`projects`, `currentProjectId`) using the already-available project dropdown context in the layout.
- Make the modal’s import endpoint robust when rendered outside `PromptTemplateController`:
  - In `yii/views/prompt-template/_import-modal.php`, ensure the fetch URL uses an absolute route to the template import endpoint (e.g., `Url::to(['/prompt-template/import-markdown'])`) so it works from any page/controller.
- Avoid duplicate modal markup:
  - Remove or guard the `yii/views/prompt-template/index.php` render of `_import-modal.php` since the layout now provides it globally; keep the existing “Import from MD” button and ensure it still targets the same modal id.

Validation (must do)
- Manual UI check (describe steps + result): logged in, click navbar “Create from MD”, choose a markdown file, import, confirm you land on the Template create page with project/name prefilled and Quill loaded with the converted delta.
- Confirm the Templates page “Import from MD” button still opens the same modal and imports successfully.

Testing
- If you can run tests locally, run the smallest relevant test command(s) (Codeception unit/functional). If you can’t, state why.

Response format
- List touched files (paths).
- Briefly describe the change per file.
- List exact test command(s) run (or why none).
```

## 6) Acceptance Criteria
- Navbar shows “Create from MD” immediately after “Generate” for authenticated users.
- Clicking “Create from MD” opens a file upload widget (modal) without navigating away first.
- Importing a Markdown file results in a Quill editor page with the converted content loaded and editable.
- Only one `#importMarkdownModal` exists on any page; no duplicate IDs.
- Existing template “Import from MD” flow continues to work.

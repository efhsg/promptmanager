# Context — export-content-locally

## Goal
Add a "Download" destination option to the Export Content modal in the Quill editor, allowing users to download content as a file to their local filesystem via the browser.

## Scope
- Single file change: `yii/views/layouts/_export-modal.php`
- No backend changes needed
- No new PHP classes or endpoints
- Frontend-only: HTML + inline JS modifications

## Key References
- Spec: `.claude/design/export-content-locally/spec.md`
- Current file: `yii/views/layouts/_export-modal.php`
- Reused endpoint: `/note/convert-format`
- Pattern reference: existing clipboard/file export logic in same file

# Quill Export Widget — Implementation Insights

## Decisions

- ExportModal JavaScript is inline in `_export-modal.php` (not in editor-init.js) — keeps modal logic self-contained
- setupExportButton() in editor-init.js is minimal wrapper that calls window.ExportModal.open()
- Project data (hasRoot) is passed to JavaScript via a global window variable per form (noteProjectData, contextProjectData, templateProjectData)
- Export button click handler reads current project selection dynamically to support project changes

## Findings

- Note can have `project_id = null` — handled with conditional hasRoot attribute
- Context and PromptTemplate always have project_id
- PathService.resolveRequestedPath() handles both existing and new file paths correctly

## Pitfalls

- npm build-init requires Docker environment with npm install first:
  ```bash
  docker compose run --entrypoint bash pma_npm -c "npm install && npm run build-init"
  ```

## Implementation Complete

All tasks completed successfully:
- Backend: FileExportService, FileExportServiceTest, ExportController
- Frontend: _export-modal.php, layouts/main.php updated
- JavaScript: setupExportContent() added to editor-init.js as toolbar widget
- Form integrations: note, context, prompt-template forms updated with toolbar button
- Linter: 0 issues
- Unit tests: 963 tests, 0 errors, 0 failures

## Refactor: Export Button → Toolbar Widget

Changed from standalone Bootstrap button to Quill toolbar widget:
- Added `setupExportContent(quill, hidden, config)` following `setupClearEditor` pattern
- Config uses callback functions: `getProjectId()`, `getEntityName()`, `getHasRoot()`
- Added `{ 'exportContent': [] }` to toolbar config arrays
- Removed standalone `<button id="export-content-btn">` from forms
- Added `exportContent` to noop handlers to prevent Quill warnings

## Post-deployment Action Required

The JavaScript minification failed because node_modules wasn't installed in the container. Run this command to complete the build:

```bash
docker compose run --entrypoint bash pma_npm -c "npm install && npm run build-init"
```

The non-minified source (`editor-init.js`) is already copied to the output directory, so the feature will work - it just won't be minified until this command is run.

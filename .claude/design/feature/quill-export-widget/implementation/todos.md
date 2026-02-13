# Quill Export Widget — Implementation Todos

## Backend

- [x] 1. Create `FileExportService.php` — export logica en validatie
- [x] 2. Create `FileExportServiceTest.php` — unit tests
- [x] 3. Create `ExportController.php` — file export endpoint

## Frontend Modal

- [x] 4. Create `_export-modal.php` — modal HTML structuur
- [x] 5. Update `layouts/main.php` — include modal partial

## JavaScript

- [x] 6. Add `setupExportButton()` to `editor-init.js` — modal trigger en export flow
- [x] 7. Run `npm run build-init` — minify (requires Docker)

## Form Integraties

- [x] 8. Update `note/_form.php` — add export button en data attributes
- [x] 9. Update `context/_form.php` — add export button en data attributes
- [x] 10. Update `prompt-template/_form.php` — add export button en data attributes

## Verification

- [x] 11. Run linter — 0 issues
- [x] 12. Run unit tests — 963 tests, 0 errors, 0 failures

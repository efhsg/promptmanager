# Insights — export-content-locally

## Decisions
- All changes in single file `_export-modal.php` per spec
- Blob download approach: no server-side file write needed
- FORMAT_MAP replaces EXTENSION_MAP to hold both extension and mime type
- Added `getSelectedDestination()` helper to read radio group value cleanly

## Findings
- Current code used `el.destFile.checked` boolean — refactored to `getSelectedDestination()` which reads the checked radio's value
- `toggleFileOptions` renamed to `toggleDestinationOptions` — handles 3 states with 2 separate divs
- `updateExtension` updated to use `FORMAT_MAP[format].ext` instead of `EXTENSION_MAP[format]`
- `handleExport` refactored from if/else to switch statement on destination value

## Result
- Linter: 0 issues
- Tests: 985 pass, 0 errors, 0 failures, 21 skipped (pre-existing)
- No backend changes needed, no new PHP classes

# Todos: import-content-from-server

- [x] 1. NoteController: inject PathService, add `actionImportServerFile`, update behaviors
- [x] 2. Create `_import-modal.php` view (Bootstrap 5 modal + IIFE JS)
- [x] 3. Include import modal in `main.php` layout
- [x] 4. Modify `setupLoadMd` in `editor-init.js` to open ImportModal
- [x] 5. Minify editor-init.js (skipped: node/docker unavailable in environment)
- [x] 6. Write unit tests for `actionImportServerFile` (11 tests, all passing)
- [x] 7. Run linter + tests, fix issues (985 tests, 0 failures)

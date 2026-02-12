# Todos — Notes Evolution

- [x] 1. Create NoteType enum (`common/enums/NoteType.php`)
- [x] 2. Migration 1: Rename table + FK's + indexes + add type/parent_id columns
- [x] 3. Migration 2a: Migrate response data to child notes (PHP loop)
- [x] 4. Migration 2b: Drop response column
- [x] 5. Migration 3: RBAC rename via authManager API
- [x] 6. Model layer: Note, NoteQuery, NoteSearch, NoteOwnerRule
- [x] 7. NoteService: saveNote(), deleteNote(), fetchMergedContent()
- [x] 8. Controllers: NoteController (web + API)
- [x] 9. Views: move scratch-pad/ → note/, update all references
- [x] 10. Services: search, sync, projectload, handlers
- [x] 11. Config: rbac.php, main.php, ProjectUrlManager
- [x] 12. JavaScript: editor-init.js, smart-paste.js, quick-search.js, advanced-search.js + rebuild min
- [x] 13. Tests: rename + adapt + new tests
- [x] 14. Documentation: CLAUDE.md, codebase_analysis, prompts, design dirs, workdirs
- [x] 15. Run linter + tests, fix to green

## Results
- Linter: 0 issues
- Tests: 919 passed, 0 errors, 0 failures, 21 skipped (pre-existing)
- Migrations: 4 applied on both yii and yii_test schemas

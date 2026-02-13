# Todos: Note Index Search

## Implementation Steps (Phase 1: Search & Type Filter) - COMPLETED

- [x] 1. Update `NoteSearch.php`: voeg `$q` property toe als search term
- [x] 2. Update `NoteSearch::rules()`: voeg `q` toe aan safe attributes
- [x] 3. Update `NoteSearch::search()`: gebruik `searchByTerm()` wanneer `$this->q` gevuld is
- [x] 4. Update `views/note/index.php`: voeg zoekformulier toe boven GridView
- [x] 5. Update `views/note/index.php`: voeg type dropdown toe met NoteType::labels()
- [x] 6. Update `views/note/index.php`: voeg reset-knop toe
- [x] 7. Test: schrijf unit test voor NoteSearch met zoekterm
- [x] 8. Test: schrijf unit test voor NoteSearch met type filter
- [x] 9. Run linter en fix issues
- [x] 10. Run unit tests en verificeer

## Implementation Steps (Phase 2: Project Dropdown) - COMPLETED

- [x] 11. Update `projects.php` fixture: voeg tweede project toe voor user 100
- [x] 12. Update `notes.php` fixture: voeg note toe in tweede project voor user 100
- [x] 13. Update `NoteSearch.php`: voeg `$project_id` attribuut toe
- [x] 14. Update `NoteSearch::rules()`: voeg `project_id` integer regel toe
- [x] 15. Update `NoteSearch::search()`: implementeer project_id filtering met context default
- [x] 16. Update `NoteController::actionIndex()`: voeg `$projectOptions` toe
- [x] 17. Update `views/note/index.php`: voeg project dropdown toe
- [x] 18. Update `views/note/index.php`: pas reset-conditie aan voor project filter
- [x] 19. Test: `testSearchFiltersOnProjectId()`
- [x] 20. Test: `testSearchWithAllProjectsShowsAllUserNotes()`
- [x] 21. Test: `testSearchDefaultsToContextProject()`
- [x] 22. Test: `testSearchDefaultsToAllProjectsWhenContextIsAll()`
- [x] 23. Run linter en fix issues
- [x] 24. Run unit tests en verificeer
- [x] 25. Fix ProjectServiceTest (affected by new fixture data)

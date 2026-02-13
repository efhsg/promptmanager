# Insights: Note Index Search

## Decisions

- **Search attribute naam**: Gebruik `q` (kort, conventioneel voor search queries)
- **Content zoeken**: `searchByTerm()` zoekt al in JSON content met LIKE, wat voldoende is voor Quill Delta
- **Type filter**: Werkt al in NoteSearch, alleen dropdown in view nodig
- **Project dropdown**: Geen `prompt` optie, "All Projects" is eerste item in `$projectOptions`
- **Context default**: Model zet `project_id` naar context default als geen form input

## Findings

- `NoteSearch` extend `Note` model, dus alle Note attributes zijn beschikbaar
- `searchByTerm()` in NoteQuery combineert name + content search met OR
- Type filter in NoteSearch:55 werkt al met `andFilterWhere(['type' => $this->type])`
- `ProjectContext::ALL_PROJECTS_ID = -1` wordt gebruikt als "All Projects" waarde
- `forUserWithProject()` scope filtert op user + specifiek project
- `forUser()` scope filtert alleen op user (alle projecten)

## Pitfalls

- Self-referencing foreign key (parent_id) in note table vereist correcte fixture ordering
- Fixture uitbreiding (project4, note7) breekt bestaande tests die exacte counts verwachten
- View file linter (PSR-12) geeft false positives op mixed PHP/HTML templates — negeren

## Implementation Complete

### Phase 1: Search & Type Filter
**Linter**: 0 issues
**Unit Tests**: 945 tests passed, 21 skipped (NoteSearchTest: 12 tests, 26 assertions)

### Phase 2: Project Dropdown
**Linter**: 0 issues (view file warnings zijn false positives)
**Unit Tests**: 949 tests passed, 21 skipped (NoteSearchTest: 16 tests, 33 assertions)

### Files Changed
- `yii/models/NoteSearch.php` — Added `project_id` attribute, import `ProjectContext`, search filtering logic
- `yii/controllers/NoteController.php` — Added `$projectOptions` to view
- `yii/views/note/index.php` — Added project dropdown, updated reset-conditie
- `yii/tests/fixtures/data/projects.php` — Added project4 (id=4) for user 100
- `yii/tests/fixtures/data/notes.php` — Added note7 (id=7) in project 4 for user 100
- `yii/tests/unit/models/NoteSearchTest.php` — Added 4 new tests for project filtering
- `yii/tests/unit/services/ProjectServiceTest.php` — Fixed assertions for new fixture data

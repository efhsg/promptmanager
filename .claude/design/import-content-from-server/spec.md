# Feature: import-content-from-server

## Samenvatting

Uitbreiding van de Quill editor "Load MD" toolbar button met een optie om markdown/tekstbestanden van de server te laden, naast de bestaande client-side upload. Volgt dezelfde modal-structuur als de Export Content feature.

## User story

We hebben nu in de Quill edit een widget "load markdown file". De gebruiker kan alleen een bestand van de client laden.

1. Breid de functionaliteit uit met een optie om bestanden van de server te laden.
2. Sluit aan bij "Export content" oplossing

## Functionele requirements

### FR-1: Load MD button opent Import Modal

- Beschrijving: De bestaande "Load MD" toolbar button opent een modal waarin de gebruiker kan kiezen tussen client upload of server file selectie
- Acceptatiecriteria:
  - [ ] Klik op Load MD button opent Import Modal
  - [ ] Modal toont source toggle (Client / Server)
  - [ ] Client optie is default geselecteerd (backwards compatible)
  - [ ] Bij client optie: file input voor upload
  - [ ] Bij server optie: file selector met autocomplete

### FR-2: Server file selectie

- Beschrijving: Gebruiker kan een bestand selecteren van de server via autocomplete, gefilterd op toegestane extensies
- Acceptatiecriteria:
  - [ ] Server optie alleen beschikbaar als project root_directory is geconfigureerd
  - [ ] File selector toont alleen .md, .markdown, .txt bestanden
  - [ ] Autocomplete filtert tijdens typen
  - [ ] Project root directory wordt getoond als context
  - [ ] Blacklisted directories worden gerespecteerd

### FR-3: Server file import

- Beschrijving: Na selectie wordt het server bestand geladen en in de editor geplaatst
- Acceptatiecriteria:
  - [ ] Bestand wordt gelezen via AJAX endpoint
  - [ ] Content wordt geconverteerd naar Quill Delta
  - [ ] Markdown wordt geparsed (indien .md/.markdown)
  - [ ] Content wordt toegevoegd op cursor positie of vervangt lege editor
  - [ ] Succesmelding toont bestandsnaam

### FR-4: Client file upload (behouden)

- Beschrijving: Bestaande client-side upload blijft werken via modal
- Acceptatiecriteria:
  - [ ] File input accepteert .md, .markdown, .txt
  - [ ] Upload werkt zoals voorheen via `/note/import-markdown`
  - [ ] Geen regressie in bestaande functionaliteit

## Gebruikersflow

1. Gebruiker klikt op "Load MD" button in Quill toolbar
2. Import Modal opent met source toggle (Client/Server)
3. **Client flow (default):**
   - Gebruiker selecteert file via file input
   - Klik Import → file wordt geüpload en geparsed
   - Content verschijnt in editor
4. **Server flow:**
   - Gebruiker schakelt naar Server tab
   - (Indien geen project root: melding + disabled)
   - Autocomplete toont beschikbare bestanden
   - Gebruiker selecteert bestand
   - Klik Import → file content wordt opgehaald
   - Content verschijnt in editor
5. Modal sluit, success toast verschijnt

## Edge cases

| Case | Gedrag |
|------|--------|
| Project heeft geen root_directory | Server optie disabled met tooltip uitleg |
| Project root niet toegankelijk | Error melding bij laden file list |
| Bestand niet meer aanwezig | Error: "File not found" |
| Bestand te groot (>1MB) | Error: "File exceeds size limit" |
| Blacklisted directory | Bestanden in blacklisted dirs niet getoond |
| Lege editor | Content vervangt alles |
| Editor heeft content | Content wordt ingevoegd op cursor positie |
| Geen project context | Server optie disabled |
| Geen allowed extensions | Default naar .md, .markdown, .txt |

## Entiteiten en relaties

### Bestaande entiteiten

- **Project** — `root_directory`, `allowed_file_extensions`, `blacklisted_directories` voor pad validatie
- **PathService** — `collectPaths()` voor file listing, `resolveRequestedPath()` voor validatie

### Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| ImportModal | View/JS | `yii/views/layouts/_import-modal.php` | **Nieuw**: Modal voor import source selectie (inclusief `<script>` met window.ImportModal IIFE) |
| main layout | View | `yii/views/layouts/main.php` | **Wijzigen**: Include `_import-modal.php` naast bestaande `_export-modal.php` |
| setupImportContent | JS | `npm/src/js/editor-init.js` | **Nieuw**: Setup functie voor import button |
| setupLoadMd | JS | `npm/src/js/editor-init.js` | **Wijzigen**: Opent ImportModal ipv direct file input |
| actionImportServerFile | Controller | `yii/controllers/NoteController.php` | **Nieuw**: Endpoint om server file te lezen en parsen (inline logica, geen aparte service) |
| NoteController behaviors | Controller | `yii/controllers/NoteController.php` | **Wijzigen**: Voeg `import-server-file` toe aan access rules |
| NoteController constructor | Controller | `yii/controllers/NoteController.php` | **Wijzigen**: Injecteer PathService via constructor |

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| DirectorySelector | `npm/src/js/directory-selector.js` | Hergebruikt voor file autocomplete (met `type=file`) |
| ExportModal structuur | `yii/views/layouts/_export-modal.php` | Template voor Import Modal layout |
| PathService | `yii/services/PathService.php` | File path validatie en resolution |
| MarkdownParser | `yii/services/copyformat/MarkdownParser.php` | Markdown naar blocks conversie |
| QuillDeltaWriter | `yii/services/copyformat/QuillDeltaWriter.php` | Blocks naar Quill Delta |
| actionPathList | `yii/controllers/FieldController.php` | Endpoint voor file listing (reeds `type=file` support) |

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| Nieuwe modal ipv inline file input wijzigen | Consistentie met Export Modal, betere UX voor twee bronnen |
| DirectorySelector hergebruiken | Bewezen component, ondersteunt al `type=file`. Naam blijft ondanks file-gebruik voor backwards compatibility |
| Geen aparte FileImportService | Import logica is simpel (~30 regels): PathService + MarkdownParser + QuillDeltaWriter direct in controller, vergelijkbaar met bestaande actionImportMarkdown |
| actionImportServerFile in NoteController | Past bij bestaande import-* actions (import-markdown, import-text) |
| Max 1MB file size | Consistent met client-side upload limiet |
| Import extensions: .md, .markdown, .txt | Subset van project allowed_extensions. path-list endpoint filtert al op project extensions |

## Open vragen

Geen

## UI/UX overwegingen

### Layout/Wireframe

**Client mode (default):**
```
┌──────────────────────────────────────────────┐
│  Import Content                          [X] │
├──────────────────────────────────────────────┤
│                                              │
│  Source                                      │
│  ┌─────────────┐ ┌─────────────┐            │
│  │ ● Client   │ │ ○ Server   │             │
│  └─────────────┘ └─────────────┘            │
│                                              │
│  File                                        │
│  ┌────────────────────────────────────────┐  │
│  │ Choose file...                         │  │
│  └────────────────────────────────────────┘  │
│  Accepted: .md, .markdown, .txt (max 1MB)    │
│                                              │
├──────────────────────────────────────────────┤
│                    [Cancel]  [Import]        │
└──────────────────────────────────────────────┘
```

**Server mode:**
```
┌──────────────────────────────────────────────┐
│  Import Content                          [X] │
├──────────────────────────────────────────────┤
│                                              │
│  Source                                      │
│  ┌─────────────┐ ┌─────────────┐            │
│  │ ○ Client   │ │ ● Server   │             │
│  └─────────────┘ └─────────────┘            │
│                                              │
│  Project root: /path/to/project              │
│                                              │
│  File                                        │
│  ┌─────────────────────────────────────────┐ │
│  │ Start typing to search files...        ↓ │
│  └─────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────┐ │
│  │ /docs/readme.md                         │ │
│  │ /docs/guide.md                          │ │
│  │ /notes/ideas.txt                        │ │
│  └─────────────────────────────────────────┘ │
│                                              │
├──────────────────────────────────────────────┤
│                    [Cancel]  [Import]        │
└──────────────────────────────────────────────┘
```

**Behavior:** Toggle tussen modes toont/verbergt relevante sectie. Alleen actieve mode sectie zichtbaar (consistent met Export Modal).

### UI States

| State | Visueel |
|-------|---------|
| Loading (import) | Spinner in Import button, button disabled |
| Loading (file list) | Spinner in autocomplete input bij mode switch naar Server |
| Empty | File input of autocomplete leeg, Import button disabled |
| Error | Alert banner boven content met error message, role="alert" |
| Success | Modal sluit, toast "Loaded filename.md" |
| Server disabled | Server radio disabled, tooltip "Project has no root directory configured" |
| File selected (client) | Browser native file input toont filename |
| File selected (server) | Path in autocomplete input, Import button enabled |
| No files found | "No matching files" text in dropdown |

### Accessibility

- Radio buttons hebben form labels
- File input heeft aria-describedby voor formaat hints
- Error messages hebben role="alert"
- Focus trap in modal
- Escape sluit modal
- Keyboard navigatie in autocomplete (pijltjes, Enter)

## Technische overwegingen

### Backend

**Nieuw endpoint: `POST /note/import-server-file`**

Request:
```json
{
  "project_id": 123,
  "path": "/docs/readme.md"
}
```

Response (success):
```json
{
  "success": true,
  "importData": {
    "content": "{\"ops\":[...]}"
  },
  "filename": "readme.md"
}
```

Response (error):
```json
{
  "success": false,
  "message": "File not found or not accessible."
}
```

**Validaties:**
- Project ownership check (behaviors: `import-server-file` in access rules met `@` role)
- Path binnen project root_directory (via PathService.resolveRequestedPath)
- Symlink resolution via realpath() voorkomt symlink attacks
- Path niet in blacklisted directories
- File extension in allowed list (.md, .markdown, .txt)
- File size <= 1MB
- File readable
- Error messages bevatten geen absolute paden (security)

**Controller implementation sketch:**
```php
public function actionImportServerFile(): array
{
    Yii::$app->response->format = Response::FORMAT_JSON;

    $data = json_decode(Yii::$app->request->rawBody, true);
    $projectId = (int) ($data['project_id'] ?? 0);
    $path = (string) ($data['path'] ?? '');

    $project = Project::find()->findUserProject($projectId, Yii::$app->user->id);
    if ($project === null || empty($project->root_directory)) {
        return ['success' => false, 'message' => 'Invalid project.'];
    }

    $absolutePath = $this->pathService->resolveRequestedPath(
        $project->root_directory,
        $path,
        $project->getBlacklistedDirectories()
    );

    // Validate file exists, extension, size...
    // Read and parse markdown...
    // Return Quill Delta JSON
}
```

### Frontend

**ImportModal API:**
```javascript
window.ImportModal.open({
    projectId: 123,
    hasRoot: true,
    rootDirectory: '/path/to/project',
    onImport: (deltaContent) => {
        // Insert into Quill editor
    }
});
```

**Modal HTML structuur:**
- Import button: `<i class="bi bi-box-arrow-in-down"></i> Import` (consistent icoon-stijl met Export)
- CSRF token via `getCsrfToken()` helper (zelfde als ExportModal)
- Bootstrap 5 modal met `modal-dialog` class

**setupLoadMd wijziging:**
- Verwijder inline file input creatie
- Open ImportModal met project config van data attributes
- Pass callback voor content insertion in Quill

**DirectorySelector gebruik:**
```javascript
const fileSelector = new DirectorySelector({
    inputElement: document.getElementById('import-file-input'),
    dropdownElement: document.getElementById('import-file-dropdown'),
    pathListUrl: '/field/path-list',
    onSelect: (path) => updatePreview(path)
});

// Load files (not directories)
await fileSelector.load(projectId, 'file');
```

**Server import request:**
```javascript
fetch(URLS.importServerFile, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({ project_id: projectId, path: selectedPath })
});
```

## Test scenarios

### Fixture requirements

- **Project met root_directory**: Project fixture met `root_directory` pointing naar test filesystem
- **Project met blacklist**: Project met `blacklisted_directories` = `['vendor']`
- **Test files**: `.md` en `.txt` bestanden in test filesystem
- **Large file**: 2MB test file voor size limit test

### Unit tests

| Test | Input | Verwacht resultaat (HTTP) |
|------|-------|--------------------------|
| actionImportServerFile: read valid markdown | `/docs/readme.md` | 200, `success: true`, Quill Delta JSON |
| actionImportServerFile: read plain text | `/notes/ideas.txt` | 200, `success: true`, Quill Delta JSON (plain) |
| actionImportServerFile: path traversal attempt | `/../../../etc/passwd` | 200, `success: false`, error message |
| actionImportServerFile: blacklisted path | `/vendor/autoload.php` | 200, `success: false`, error message |
| actionImportServerFile: file too large | 2MB file | 200, `success: false`, "File exceeds size limit" |
| actionImportServerFile: file not found | `/nonexistent.md` | 200, `success: false`, "File not found" |
| actionImportServerFile: wrong project | Other user's project_id | 200, `success: false`, "Invalid project" |
| actionImportServerFile: no project_id | `{}` | 200, `success: false`, "Invalid project" |
| actionImportServerFile: extension not allowed | `/file.php` | 200, `success: false`, error message |

### Regressie tests

| Test | Wat te verifiëren |
|------|------------------|
| Bestaande Load MD via file input | Zorg dat client upload flow blijft werken |
| actionImportMarkdown | Endpoint moet ongewijzigd blijven |
| actionImportText | Endpoint moet ongewijzigd blijven |

### Security tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| Symlink attack | Symlink naar /etc/passwd in project root | Error: resolved path outside root |
| Null byte injection | `/docs/readme.md%00.txt` | Error: invalid path |
| Error message leak | Request non-existent file | Error without absolute path |
| Unauthenticated access | No session | 401 Unauthorized |
| CSRF validation | Missing CSRF token | 400 Bad Request |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| No project context | ImportModal.open zonder projectId | Server tab disabled |
| No root directory | Project zonder root_directory | Server tab disabled met tooltip |
| Empty file | 0 bytes markdown | Empty Quill Delta `{"ops":[{"insert":"\n"}]}` |
| Binary file renamed to .md | PNG file met .md extensie | Parse error, fallback to plain text |
| Concurrent modification | File wijzigt tijdens import | Geen probleem, leest snapshot |
| Special characters in filename | `file (1).md` | Correct escaped in path |
| Unicode content | UTF-8 markdown met emoji | Correct geparsed |

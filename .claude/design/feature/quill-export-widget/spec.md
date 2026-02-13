# Quill Export Widget — Functional Specification

## 1. Overview

Een herbruikbare Quill toolbar widget die gebruikers toestaat editor-content te exporteren naar het clipboard of een bestand in het project filesystem.

### Scope

- **In scope**: Toolbar button, modal dialoog, clipboard export, file export naar project rootX
- **Out of scope**: Cloud storage, batch export, view pages (alleen form pages)

### Aannames

- Widget wordt geïntegreerd in form pages, niet in view pages
- Directory browsing hergebruikt bestaande `FieldController::actionPathList()` endpoint

## 2. User Stories

### US-1: Export naar clipboard
> Als gebruiker wil ik editor-content kunnen kopiëren in een gekozen formaat zodat ik het kan plakken in andere applicaties.

### US-2: Export naar bestand
> Als gebruiker wil ik editor-content kunnen opslaan als bestand in mijn project directory zodat ik prompts of notities kan bewaren voor later gebruik.

## 3. Functional Requirements

### FR-1: Widget Activatie

| Requirement | Beschrijving |
|-------------|--------------|
| FR-1.1 | Widget verschijnt als toolbar button in elke Quill editor |
| FR-1.2 | Button opent een modal dialoog |
| FR-1.3 | Widget is optioneel per editor (via configuratie) |

### FR-2: Export Doelen

| Requirement | Beschrijving |
|-------------|--------------|
| FR-2.1 | **Clipboard** — altijd beschikbaar |
| FR-2.2 | **File** — alleen beschikbaar als de "context project" een `root_directory` heeft |

**Context project definitie:**
- Note: `note.project_id` → `project.root_directory` (Note kan `project_id = null` hebben → geen File optie)
- Context: `context.project_id` → `project.root_directory` (altijd gekoppeld aan project)
- PromptTemplate: `prompt_template.project_id` → `project.root_directory` (altijd gekoppeld aan project)

**Note zonder project**: Als `note.project_id` is `null`, is File export niet beschikbaar. De widget toont alleen Clipboard optie.

### FR-3: Export Formaten

| Formaat | Extensie | Beschrijving |
|---------|----------|--------------|
| Markdown | `.md` | **Default** — GitHub-flavored markdown |
| Plain Text | `.txt` | Gestripte tekst zonder opmaak |
| HTML | `.html` | HTML markup |
| Quill Delta JSON | `.json` | Ruwe Quill Delta structuur |
| XML | `.xml` | LLM-XML formaat met `<instructions>` tags |

### FR-4: Modal Interface

#### FR-4.1 Layout

```
┌─────────────────────────────────────────────────┐
│  Export Content                            [X]  │
├─────────────────────────────────────────────────┤
│                                                 │
│  Destination                                    │
│  [Clipboard] [File]     ← segmented button      │
│                                                 │
│  Format                                         │
│  [▼ Markdown                              ]     │
│                                                 │
│  ─────────────────────────────────────────────  │
│  [ Alleen zichtbaar bij "File" ]                │
│                                                 │
│  Filename                                       │
│  [{entity-name}                      .md]       │
│                               [Suggest]         │
│                                                 │
│  Directory                                      │
│  [/                               ] [Browse]    │
│                                                 │
│  Preview path:                                  │
│  /path/to/project/{entity-name}.md              │
│                                                 │
├─────────────────────────────────────────────────┤
│                           [Cancel]  [Export]    │
└─────────────────────────────────────────────────┘
```

**UI-keuzes:**
- **Destination**: Segmented button (Bootstrap 5 btn-group met radio inputs) in plaats van radio buttons — cleaner voor 2 opties
- **Extension**: Inline suffix binnen inputveld (readonly, visueel onderscheiden) — verdwijnt niet, altijd zichtbaar
- **Preview path**: Update real-time bij wijziging van filename, directory, of format

#### FR-4.2 Destination Selection

| Situatie | Gedrag |
|----------|--------|
| Project heeft `root_directory` | Beide opties beschikbaar, Clipboard is default |
| Project heeft geen `root_directory` | Alleen Clipboard, "File" optie verborgen of disabled |
| Geen project context | Alleen Clipboard beschikbaar |

#### FR-4.3 File-specifieke velden

| Veld | Type | Validatie |
|------|------|-----------|
| Filename | Text input + Suggest button | Verplicht, geen path separators, geen speciale karakters |
| Extension | Readonly label | Automatisch gebaseerd op Format selectie |
| Directory | Path selector | Moet binnen `root_directory` vallen, respecteert blacklist |

#### FR-4.4 Filename Defaults en Suggestie

| Requirement | Beschrijving |
|-------------|--------------|
| FR-4.4.1 | Default filename is entity naam (note name, template name, context name), sanitized voor filesystem |
| FR-4.4.2 | Entity naam wordt doorgegeven via `data-export-entity-name` attribuut op editor container |
| FR-4.4.3 | "Suggest" button vraagt Claude om een naam te genereren op basis van content |
| FR-4.4.4 | Suggest gebruikt bestaande `/claude/suggest-name` endpoint |
| FR-4.4.5 | Tijdens suggest: button toont spinner, is disabled |
| FR-4.4.6 | Bij lege content: suggest toont inline foutmelding onder filename input "Write some content first" (niet als toast) |

**Filename sanitization regels:**
- Vervang `/`, `\`, `:`, `*`, `?`, `"`, `<`, `>`, `|` door `-`
- Trim leading/trailing whitespace en dots
- Max 200 karakters (ruimte voor extensie + path)

### FR-5: Directory Browser

| Requirement | Beschrijving |
|-------------|--------------|
| FR-5.1 | Directory input met autocomplete dropdown |
| FR-5.2 | Root is altijd project's `root_directory` |
| FR-5.3 | Alleen directories tonen (geen bestanden) — fetch via `/field/path-list?projectId={id}&type=directory` |
| FR-5.4 | Respecteert `blacklisted_directories` configuratie |
| FR-5.5 | Default selectie is root (`/`) |
| FR-5.6 | Bij typing: filter beschikbare directories met 200ms debounce, toon matches in dropdown |

**Implementatie**: Hergebruik `PathSelectorWidget` JavaScript logica. De modal bouwt een vergelijkbare autocomplete, gebruikmakend van dezelfde `window.pathSelectorWidgets` pattern. De widget zelf wordt niet geïnstantieerd in de modal; alleen de fetch/filter logica wordt gedeeld.

**Verificatie**: `type=directory` is een geldige waarde — zie `FieldConstants::PATH_FIELD_TYPES` in `yii/common/constants/FieldConstants.php:10`.

### FR-6: Export Acties

#### FR-6.1 Clipboard Export

1. Toon loading state op Export button (spinner, disabled)
2. Converteer Quill Delta naar geselecteerd formaat (server-side via bestaande `CopyFormatConverter`)
3. Kopieer naar clipboard
4. Toon success toast: "Content copied to clipboard"
5. Sluit modal

#### FR-6.2 File Export

1. Valideer filename en directory
2. Valideer dat volledig pad binnen `root_directory` valt en niet geblacklist is
3. Converteer Quill Delta naar geselecteerd formaat
4. Schrijf naar bestand
5. Bij success: toast "Saved to {filename}.{ext}"
6. Bij fout: toon error in modal (niet sluiten)
7. Bij success: sluit modal

### FR-7: Bestand Overschrijf Gedrag

| Requirement | Beschrijving |
|-------------|--------------|
| FR-7.1 | Als bestand bestaat: toon waarschuwing |
| FR-7.2 | Waarschuwingstekst: "File already exists. Overwrite?" |
| FR-7.3 | Extra bevestiging nodig via checkbox of modal |
| FR-7.4 | Default: niet overschrijven |

## 4. Edge Cases

### EC-1: Lege editor content

| Scenario | Gedrag |
|----------|--------|
| Editor is leeg | Export button disabled OF toon warning bij klik |
| Alleen whitespace | Behandel als leeg |

**Definitie leeg:** `quill.getLength() <= 1` (Quill heeft altijd 1 newline)

### EC-2: Project context wijzigt

| Scenario | Gedrag |
|----------|--------|
| Gebruiker wijzigt project dropdown terwijl modal open | Modal data herladen (directory root, file optie availability) |
| Project wordt verwijderd terwijl modal open | Bij export: foutmelding "Project not found" |

### EC-3: Directory niet meer toegankelijk

| Scenario | Gedrag |
|----------|--------|
| `root_directory` verwijderd/verplaatst | Export mislukt met duidelijke foutmelding |
| Directory in blacklist | Niet selecteerbaar in browser, export wordt geweigerd |

### EC-4: Bestandsnaam conflicten

| Scenario | Gedrag |
|----------|--------|
| Filename bevat `/` of `\` | Validatiefout: "Filename cannot contain path separators" |
| Filename bevat ongeldige karakters | Validatiefout: "Invalid characters in filename" |
| Filename te lang | Validatiefout: "Filename too long (max 200 characters)" |

### EC-5: Concurrent access

| Scenario | Gedrag |
|----------|--------|
| Bestand wordt tijdens export gewijzigd door ander proces | Standaard OS gedrag (laatste write wint) |
| Geen schrijfrechten | Export mislukt: "Permission denied" |

### EC-6: Grote content

| Scenario | Gedrag |
|----------|--------|
| Zeer grote Delta (>1MB) | Server limiet van ~5MB body geldt (standaard PHP/Nginx config) |
| Content > 1MB | Geen client-side warning — server retourneert 413 error indien overschreden |

**Beslissing**: Geen client-side limiet check. In praktijk zullen prompts/notes zelden >1MB zijn.

### EC-7: Project allowed_file_extensions conflict

| Scenario | Gedrag |
|----------|--------|
| Project heeft `allowed_file_extensions` whitelist | Export negeert whitelist — whitelist geldt alleen voor file/directory field *reading*, niet voor export *writing* |

**Rationale**: De whitelist is bedoeld om te beperken welke bestanden de gebruiker kan *selecteren* als input, niet welke formaten ze kunnen *exporteren*.

## 5. Acceptatiecriteria

### AC-1: Widget Beschikbaarheid

- [ ] Widget is zichtbaar in Note editor (`note/_form.php`)
- [ ] Widget is zichtbaar in Context editor (`context/_form.php`)
- [ ] Widget is zichtbaar in PromptTemplate editor (`prompt-template/_form.php`)
- [ ] Widget kan optioneel uitgezet worden per editor

### AC-2: Clipboard Export

- [ ] Gebruiker kan content kopiëren als Markdown
- [ ] Gebruiker kan content kopiëren als Plain Text
- [ ] Gebruiker kan content kopiëren als HTML
- [ ] Gebruiker kan content kopiëren als Quill Delta JSON
- [ ] Gebruiker kan content kopiëren als XML
- [ ] Na kopiëren verschijnt success toast

### AC-3: File Export

- [ ] File optie alleen zichtbaar als project `root_directory` heeft
- [ ] Directory browser toont alleen toegestane directories
- [ ] Bestandsnaam validatie werkt correct
- [ ] Bestand wordt correct geschreven naar filesystem
- [ ] Extensie matcht geselecteerd formaat
- [ ] Overschrijf-waarschuwing verschijnt bij bestaand bestand
- [ ] Default filename is entity naam (sanitized)
- [ ] Suggest button genereert naam via Claude

### AC-4: Error Handling

- [ ] Lege editor toont passende feedback
- [ ] Ongeldige bestandsnaam toont validatiefout
- [ ] Niet-bestaande directory toont foutmelding
- [ ] Schrijffout toont duidelijke foutmelding

### AC-5: UX

- [ ] Modal volgt bestaande Bootstrap 5 styling
- [ ] Keyboard navigatie werkt:
  - [ ] Tab cyclet door alle focusable elementen binnen modal (focus trap)
  - [ ] Enter op Export button triggert export
  - [ ] Escape sluit modal
- [ ] Loading state tijdens export operatie (beide destinations)
- [ ] Duidelijke feedback bij success/failure
- [ ] Preview path update real-time bij wijziging filename/directory/format

## 6. Dependencies

### Bestaande Components (Hergebruik)

| Component | Locatie | Hergebruik |
|-----------|---------|------------|
| `CopyFormatConverter` | `yii/services/CopyFormatConverter.php` | Format conversie (injecteren in service) |
| `PathService` | `yii/services/PathService.php` | Path validatie en blacklist check |
| `FieldController::actionPathList()` | `yii/controllers/FieldController.php` | Directory listing endpoint (hergebruiken, niet dupliceren) |
| `QuillToolbar` | `npm/src/js/editor-init.js` | Toolbar button setup |
| `CopyType` enum | `yii/common/enums/CopyType.php` | Format definities |
| `NoteController::actionConvertFormat()` | `yii/controllers/NoteController.php` | Clipboard format conversie endpoint |

### Nieuwe Components (te creëren)

| Component | Type | Locatie | Verantwoordelijkheid |
|-----------|------|---------|----------------------|
| `setupExportButton()` | JS functie | `npm/src/js/editor-init.js` | Toolbar button, modal trigger, export flow — volgt bestaand `setupCopyButton()` pattern |
| `_export-modal.php` | PHP partial | `yii/views/layouts/` | Modal HTML structuur (gedeeld door alle editors, past bij bestaande `_advanced-search-modal.php` pattern) |
| `ExportController` | Controller | `yii/controllers/ExportController.php` | `actionToFile()` voor file export |
| `FileExportService` | Service | `yii/services/FileExportService.php` | File write met validatie, path resolution |

### Data Attributes voor Widget Configuratie

De export widget ontvangt configuratie via data-attributes op de Quill container:

| Attribute | Beschrijving | Voorbeeld |
|-----------|--------------|-----------|
| `data-export-enabled` | Widget aan/uit | `"true"` |
| `data-export-entity-name` | Default filename | `"My Note"` |
| `data-export-project-id` | Project ID voor path resolution | `"123"` of `""` (leeg = geen project) |
| `data-export-root-directory` | Project root (voor UI feedback) | `"/var/projects/my-project"` of `""` |
| `data-export-has-root` | Marker: aanwezigheid = File optie beschikbaar | attribuut aanwezig = true, afwezig = false |

## 7. Security Considerations

| Aspect | Mitigatie |
|--------|-----------|
| Path traversal | `PathService::resolveRequestedPath()` valideert binnen root |
| Blacklist bypass | Server-side blacklist check voor write via `PathService` |
| XSS in filename | Filename sanitization in `FileExportService`, `Html::encode()` in views |
| CSRF | Standaard Yii CSRF token validatie |
| Unauthorized write | RBAC check op project ownership via `ProjectOwnerRule` in `ExportController` |
| Symlink escape | `realpath()` in PathService voorkomt symlink-based path traversal |
| File type spoofing | Extensie wordt server-side bepaald o.b.v. `CopyType` enum, niet user input |

## 8. Format-Extensie Mapping

Mapping wordt bepaald door `CopyType` enum en `FileExportService`:

| CopyType | Extensie |
|----------|----------|
| `CopyType::MD` | `.md` |
| `CopyType::TEXT` | `.txt` |
| `CopyType::HTML` | `.html` |
| `CopyType::QUILL_DELTA` | `.json` |
| `CopyType::LLM_XML` | `.xml` |

## 9. API Contract

### Clipboard Export (bestaand — hergebruik)

Hergebruikt bestaande `/note/convert-format` endpoint:

**POST `/note/convert-format`**
```json
{
    "content": "{\"ops\":[...]}",
    "format": "md"
}
```

Response: `{ "success": true, "content": "# Converted markdown..." }`

### Directory Listing (bestaand — hergebruik)

Hergebruikt bestaande `/field/path-list` endpoint:

**GET `/field/path-list?projectId=123&type=directory`**

Response: `{ "success": true, "root": "/path/to/project", "paths": ["/", "/docs", "/src"] }`

### File Export (nieuw)

**POST `/export/to-file`**

**Request:**
```json
{
    "content": "{\"ops\":[...]}",
    "format": "md",
    "filename": "my-export",
    "directory": "/docs",
    "project_id": 123,
    "overwrite": false
}
```

**Response (success):**
```json
{
    "success": true,
    "path": "/docs/my-export.md",
    "message": "File saved successfully."
}
```

**Response (file exists, overwrite=false):**
```json
{
    "success": false,
    "exists": true,
    "path": "/docs/my-export.md",
    "message": "File already exists."
}
```

**Response (error):**
```json
{
    "success": false,
    "message": "Permission denied."
}
```

## 10. Implementatie Details

### ExportController

```php
<?php

namespace app\controllers;

use app\services\FileExportService;
use common\enums\CopyType;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class ExportController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly FileExportService $exportService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'to-file' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['to-file'],
                        'allow' => true,
                        'roles' => ['@'],
                        // Note: Project ownership wordt gevalideerd in FileExportService
                        // via findUserProject() — consistent met andere AJAX endpoints
                    ],
                ],
            ],
        ];
    }

    public function actionToFile(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $rawBody = Yii::$app->request->rawBody;
        if ($rawBody === '') {
            return ['success' => false, 'message' => 'Empty request body.'];
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $content = $data['content'] ?? '';
        $format = CopyType::tryFrom($data['format'] ?? '') ?? CopyType::MD;
        $filename = trim($data['filename'] ?? '');
        $directory = $data['directory'] ?? '/';
        $projectId = (int) ($data['project_id'] ?? 0);
        $overwrite = (bool) ($data['overwrite'] ?? false);

        if ($filename === '') {
            return ['success' => false, 'message' => 'Filename is required.'];
        }

        if ($projectId <= 0) {
            return ['success' => false, 'message' => 'Invalid project ID.'];
        }

        return $this->exportService->exportToFile(
            $content,
            $format,
            $filename,
            $directory,
            $projectId,
            Yii::$app->user->id,
            $overwrite
        );
    }
}
```

### FileExportService

```php
<?php

namespace app\services;

use app\models\Project;
use common\enums\CopyType;
use RuntimeException;

class FileExportService
{
    // Keys moeten exact matchen met CopyType::*->value
    private const EXTENSION_MAP = [
        'md' => '.md',        // CopyType::MD
        'text' => '.txt',     // CopyType::TEXT
        'html' => '.html',    // CopyType::HTML
        'quilldelta' => '.json', // CopyType::QUILL_DELTA
        'llm-xml' => '.xml',  // CopyType::LLM_XML
    ];

    public function __construct(
        private readonly CopyFormatConverter $formatConverter,
        private readonly PathService $pathService
    ) {
    }

    /**
     * @return array{success: bool, path?: string, exists?: bool, message: string}
     */
    public function exportToFile(
        string $deltaContent,
        CopyType $format,
        string $filename,
        string $directory,
        int $projectId,
        int $userId,
        bool $overwrite = false
    ): array {
        // Validate project ownership via Query class pattern
        /** @var Project|null $project */
        $project = Project::find()->findUserProject($projectId, $userId);
        if ($project === null) {
            return ['success' => false, 'message' => 'Project not found.'];
        }

        if (empty($project->root_directory)) {
            return ['success' => false, 'message' => 'Project has no root directory.'];
        }

        // Sanitize and validate filename
        $sanitizedFilename = $this->sanitizeFilename($filename);
        if ($sanitizedFilename === '') {
            return ['success' => false, 'message' => 'Invalid filename.'];
        }

        // Determine extension from format
        $extension = self::EXTENSION_MAP[$format->value] ?? '.txt';
        $fullFilename = $sanitizedFilename . $extension;

        // Resolve and validate path
        // Note: resolveRequestedPath() uses realpath() which returns false for non-existent files.
        // For new files, we validate the directory exists and construct the path manually.
        $relativePath = rtrim($directory, '/') . '/' . $fullFilename;
        $absolutePath = $this->pathService->resolveRequestedPath(
            $project->root_directory,
            $relativePath,
            $project->getBlacklistedDirectories()
        );

        // If absolutePath is null, the path is either outside root or blacklisted
        if ($absolutePath === null) {
            return ['success' => false, 'message' => 'Invalid or blacklisted path.'];
        }

        // Check if file exists
        if (file_exists($absolutePath) && !$overwrite) {
            return [
                'success' => false,
                'exists' => true,
                'path' => $relativePath,
                'message' => 'File already exists.',
            ];
        }

        // Ensure directory exists
        $targetDir = dirname($absolutePath);
        if (!is_dir($targetDir)) {
            return ['success' => false, 'message' => 'Target directory does not exist.'];
        }

        // Convert content
        $convertedContent = $this->formatConverter->convertFromQuillDelta($deltaContent, $format);

        // Write file
        $result = @file_put_contents($absolutePath, $convertedContent);
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to write file. Check permissions.'];
        }

        return [
            'success' => true,
            'path' => $relativePath,
            'message' => 'File saved successfully.',
        ];
    }

    public function sanitizeFilename(string $filename): string
    {
        // Remove path separators and invalid characters
        $sanitized = preg_replace('/[\/\\\\:*?"<>|]/', '-', $filename);
        // Trim whitespace and dots
        $sanitized = trim($sanitized, " \t\n\r\0\x0B.");
        // Limit length
        if (mb_strlen($sanitized) > 200) {
            $sanitized = mb_substr($sanitized, 0, 200);
        }

        return $sanitized;
    }
}
```

## 11. Codebase Alignment Notes

**Gevalideerde patterns:**
- `Project::find()->findUserProject()` in `ProjectQuery.php:17` — correct gebruik voor ownership check
- `CopyType` enum in `common/enums/CopyType.php` — values matchen met EXTENSION_MAP
- `PathService::resolveRequestedPath()` in `services/PathService.php:97` — exact de juiste signature
- `CopyFormatConverter::convertFromQuillDelta()` in `services/CopyFormatConverter.php:39` — input is string (Delta JSON), output is formatted string
- Controller DI pattern volgt `FieldController` en `NoteController` — constructor injection met `$id, $module, ...$services, $config = []`
- Modal partial in `layouts/` folder past bij bestaand `_advanced-search-modal.php` pattern
- `FieldConstants::PATH_FIELD_TYPES` bevat `'directory'` — endpoint hergebruik valide

**JS Pattern alignment:**
- `QuillToolbar` module in `editor-init.js` — nieuwe `setupExportButton()` moet toegevoegd worden aan return object (regel 383-390)
- Toast feedback via `showToast()` helper — al beschikbaar
- CSRF token via `getCsrfToken()` helper — al beschikbaar
- `copyWithFormat()` pattern kan hergebruikt worden voor clipboard export flow

## 12. Bestaande Implementatie Referentie

De `note/_form.php` bevat al een eenvoudige copy-to-clipboard implementatie:

```javascript
window.QuillToolbar.setupCopyButton('copy-content-btn', 'copy-format-select', () => JSON.stringify(quill.getContents()));
```

Dit bouwt voort op `QuillToolbar.copyWithFormat()` in `editor-init.js` die de `/note/convert-format` endpoint aanroept. De nieuwe widget moet deze patronen uitbreiden met:

1. Een modal interface in plaats van inline dropdown
2. File export optie naast clipboard
3. Directory browsing functionaliteit (via bestaande `/field/path-list` endpoint)

## 13. Beslissingen

| Vraag | Beslissing | Rationale |
|-------|------------|-----------|
| Filename default | Entity naam, sanitized voor filesystem | Consistente UX, voorspelbaar gedrag |
| Filename suggestie | "Suggest" button via `/claude/suggest-name` | Hergebruik bestaande functionaliteit |
| Target editors | Note, Context, PromptTemplate (form pages only) | Scope beperking; view pages hebben geen edit functionaliteit |
| History/recente paden | Nee, niet in scope | YAGNI — kan later toegevoegd worden |
| Bulk export | Niet in scope | Complexiteit; focus op single-editor export |
| Note zonder project | Alleen Clipboard optie beschikbaar | Note.project_id kan null zijn |
| Directory listing | Hergebruik `/field/path-list` endpoint | DRY — endpoint bestaat al |
| Controller structuur | Nieuwe `ExportController` | Single responsibility; geen file write logica in NoteController |
| allowed_file_extensions | Genegeerd bij export | Whitelist is voor reading, niet writing |
| Service naamgeving | `FileExportService` | Volgt architectuur: naam naar verantwoordelijkheid |
| Modal locatie | `yii/views/layouts/_export-modal.php` | Volgt bestaand pattern van `_advanced-search-modal.php` in layouts folder voor cross-view partials |

## 14. Test Scenarios

### Unit Tests (`yii/tests/unit/services/FileExportServiceTest.php`)

| Test | Verwacht resultaat |
|------|-------------------|
| `testExportWithValidPath` | Bestand geschreven, success response |
| `testExportWithBlacklistedPath` | `success=false`, message over blacklist |
| `testExportWithPathOutsideRoot` | `success=false`, message over invalid path |
| `testExportExistingFileWithoutOverwrite` | `success=false`, `exists=true` |
| `testExportExistingFileWithOverwrite` | Bestand overschreven, success response |
| `testSanitizeFilenameWithPathSeparators` | Path separators vervangen door `-` |
| `testSanitizeFilenameWithSpecialChars` | Speciale karakters vervangen door `-` |
| `testSanitizeFilenameTooLong` | Afgekapt op 200 karakters |
| `testExportWithInvalidProjectId` | `success=false`, "Project not found" |
| `testExportWithNoRootDirectory` | `success=false`, "no root directory" |

### Acceptance Tests (handmatig)

| Scenario | Stappen | Verwacht |
|----------|---------|----------|
| Clipboard export MD | Open Note form → Export → Clipboard + MD → Export | Clipboard bevat markdown |
| File export nieuwe file | Export → File → /docs/test → Export | Bestand aangemaakt, toast |
| File export bestaand | Export → File → bestaande file → Export | Waarschuwing getoond |
| Note zonder project | Open Note zonder project → Export | Alleen Clipboard zichtbaar |
| Blacklisted directory | Export → File → .git/ selecteren | Directory niet selecteerbaar |
| Empty editor | Open Note → lege editor → Export button | Button disabled of warning |

## 15. Files Overzicht

### Nieuwe bestanden

| Pad | Type | Beschrijving |
|-----|------|--------------|
| `yii/controllers/ExportController.php` | Controller | File export endpoint |
| `yii/services/FileExportService.php` | Service | Export logica en validatie |
| `yii/views/layouts/_export-modal.php` | View partial | Gedeelde modal HTML (past bij bestaand `_advanced-search-modal.php` pattern) |
| `yii/tests/unit/services/FileExportServiceTest.php` | Unit test | Service tests |

### Gewijzigde bestanden

| Pad | Wijziging |
|-----|-----------|
| `npm/src/js/editor-init.js` | Toevoegen `setupExportButton()` functie |
| `yii/views/note/_form.php` | Data attributes en export button/modal trigger toevoegen |
| `yii/views/context/_form.php` | Data attributes en export button/modal trigger toevoegen |
| `yii/views/prompt-template/_form.php` | Data attributes en export button/modal trigger toevoegen |
| `yii/views/layouts/main.php` | Include `_export-modal.php` partial (eenmalig, onderaan body) |

## 16. Review Notes

**Review datum:** 2026-02-13

**Geverifieerde codebase referenties:**
- `ProjectQuery::findUserProject()` — `yii/models/query/ProjectQuery.php:17`
- `PathService::resolveRequestedPath()` — `yii/services/PathService.php:97`
- `CopyFormatConverter::convertFromQuillDelta()` — `yii/services/CopyFormatConverter.php:39`
- `CopyType` enum values — `yii/common/enums/CopyType.php:6-11`
- `FieldConstants::PATH_FIELD_TYPES` — `yii/common/constants/FieldConstants.php:10` (bevat `'directory'`)
- `_advanced-search-modal.php` pattern — `yii/views/layouts/_advanced-search-modal.php`
- `NoteController` DI pattern — `yii/controllers/NoteController.php:41-54`
- `QuillToolbar` module — `npm/src/js/editor-init.js:383-390`

**Score:** 8.5/10 — Uitstekende specificatie, klaar voor implementatie.

## 17. Verificatie

Na implementatie uitvoeren:

```bash
# Unit tests
cd yii && vendor/bin/codecept run unit tests/unit/services/FileExportServiceTest.php

# Linter
cd yii && vendor/bin/php-cs-fixer fix --dry-run --config=../.php-cs-fixer.dist.php

# Frontend build
cd npm && npm run build-init
```

## 18. Implementatie Volgorde

Aanbevolen implementatievolgorde:

1. **Backend eerst**
   - `FileExportService.php` — core logica
   - `FileExportServiceTest.php` — unit tests
   - `ExportController.php` — endpoint

2. **Frontend modal**
   - `_export-modal.php` — modal HTML
   - `layouts/main.php` — include toevoegen

3. **JavaScript**
   - `setupExportButton()` in `editor-init.js`
   - Minify via `npm run build-init`

4. **Form integraties**
   - `note/_form.php`
   - `context/_form.php`
   - `prompt-template/_form.php`

5. **Handmatige tests**
   - Clipboard export alle formaten
   - File export met nieuwe/bestaande files
   - Note zonder project (alleen clipboard)

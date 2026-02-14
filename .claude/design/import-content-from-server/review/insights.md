# Insights

## Codebase onderzoek

### Vergelijkbare features
- **Export Content Modal** (`yii/views/layouts/_export-modal.php`): Volledige modal met clipboard/file toggle, directory selector, format selectie. Gebruikt `window.ExportModal.open()` API.
- **Load MD Button** (`npm/src/js/editor-init.js:217-301`): Huidige client-side file upload via `<input type="file">`, converteert naar Quill Delta via `/note/import-markdown` endpoint.
- **Path List endpoint** (`yii/controllers/FieldController.php:153-198`): Haalt bestanden/directories op voor project met blacklist/whitelist filtering.

### Herbruikbare componenten
- **DirectorySelector** (`npm/src/js/directory-selector.js`): Autocomplete widget voor padselectie, gebruikt `path-list` endpoint. Kan ook voor files gebruikt worden met `type=file`.
- **PathService** (`yii/services/PathService.php`): Path validatie, blacklist checking, `resolveRequestedPath()` voor absolute pad resolutie
- **ExportModal structuur**: Clipboard/File toggle pattern, project root display, preview path

### Te volgen patterns
- Modal opbouw: `yii/views/layouts/_export-modal.php` — destination toggle, format select, directory input met autocomplete
- DirectorySelector initialisatie via `new DirectorySelector({...})`
- PathService voor pad validatie
- AJAX endpoint in controller met `Response::FORMAT_JSON`

### Key observations
1. Export modal gebruikt DirectorySelector voor directory autocomplete — kan hergebruikt worden voor file selectie
2. `path-list` endpoint ondersteunt al `type=file` — geeft bestanden met toegestane extensies
3. Load MD functie voert al markdown parsing uit via `/note/import-markdown` — kan hergebruikt worden
4. Export modal heeft project context nodig (`projectId`, `hasRoot`, `rootDirectory`) — zelfde info nodig voor import
5. Huidige load MD button heeft geen project context — moet via config meegegeven worden

## Beslissingen
- Gebruik bestaande Import Modal structuur parallel aan Export Modal
- Hergebruik DirectorySelector component voor file selectie
- Hergebruik `/note/import-markdown` endpoint voor content parsing
- Voeg nieuw endpoint toe voor server file reading

## Open vragen
Geen

## Blokkades
Geen

## Eindresultaat
Spec review voltooid op 2026-02-14. Alle 6 reviews >= 8/10. Consistentiecheck passed. Klaar voor implementatie.

# Insights: export-content-locally

## Codebase onderzoek

### Vergelijkbare features
- **Import modal Client optie** (`yii/views/layouts/_import-modal.php`): Gebruikt `<input type="file">` voor lokaal bestand uploaden. Dit is het patroon dat we volgen — maar dan omgekeerd (download i.p.v. upload).
- **Export modal Clipboard optie** (`yii/views/layouts/_export-modal.php`): Converteert Quill Delta via `/note/convert-format` endpoint en kopieert naar clipboard. We hergebruiken dezelfde conversie-flow.
- **Export modal File optie** (`yii/views/layouts/_export-modal.php`): Schrijft naar server filesystem via `/export/to-file`. Vereist project root_directory.

### Herbruikbare componenten
- **Format conversie endpoint**: `/note/convert-format` — converteert Quill Delta naar MD/text/HTML/JSON/XML
- **Export modal UI**: Bestaande filename input, format selector, extension mapping
- **EXTENSION_MAP**: Reeds gedefinieerd in `_export-modal.php` JS (`md → .md`, `text → .txt`, etc.)
- **sanitizeFilename()**: Al beschikbaar in export modal JS

### Te volgen patterns
- **Import modal Client/Server toggle**: Radio buttons met `btn-group` patroon voor source selectie
- **Export modal Clipboard/File toggle**: Zelfde patroon, nu uit te breiden met derde optie
- **Browser download via Blob**: Standaard ES2019 patroon met `URL.createObjectURL` en tijdelijke `<a>` link

## Beslissingen
- De "Download" optie heeft GEEN server-side filesystem-toegang nodig — puur browser download
- De "Download" optie heeft GEEN project root_directory nodig (anders dan de "File" optie)
- De bestaande `/note/convert-format` endpoint kan hergebruikt worden voor format conversie
- Filename input wordt getoond bij zowel "File" als "Download" destination (maar directory selector alleen bij "File")
- UI-label gewijzigd van "Local" → "Download" (UX/UI review) — beschrijft de actie, niet de techniek

## Review verbeteringen
- **Architect**: FORMAT_MAP consolidatie (ext + mime in één object), `URL.revokeObjectURL()` cleanup
- **UX/UI**: Label "Local" → "Download", `bi-download` icon, success states per destination
- **Front-end**: HTML structuur opgesplitst (`export-filename-options` + `export-directory-options`), element IDs
- **Developer**: `handleExport()` refactoring boolean → switch, `getElements()` uitbreiding
- **Tester**: Edge cases dubbele klik + netwerk fout toegevoegd

## Consistentiecheck
- Terminologie "Local" → "Download" uniform gemaakt door hele spec
- Wireframe ↔ componenten: consistent
- Frontend ↔ backend: consistent
- Edge cases ↔ tests: alle gedekt
- Architectuur ↔ locaties: consistent
- Security ↔ endpoints: geen nieuwe endpoints, bestaand is beveiligd

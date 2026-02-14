# Context: export-content-locally

## Doel
De "Export Content" modal in de Quill editor uitbreiden met een optie om content als bestand te downloaden naar het lokale filesystem (browser download), naast de bestaande opties "Clipboard" en "File" (server).

## Scope
- Export modal UI aanpassen: derde destination optie "Local" / "Download"
- Client-side download triggeren via Blob/URL.createObjectURL
- Server-side format conversie hergebruiken (bestaand `/note/convert-format` endpoint)
- Filename input hergebruiken uit bestaande file-export sectie

## User Story
The "export content" function in the Quill editor can now only export to the project root on the server. We also want the option to save to the local filesystem. For the "Import content" we do something similar. We can follow that pattern.

## Referenties
- Export modal: `yii/views/layouts/_export-modal.php`
- Import modal (patroon): `yii/views/layouts/_import-modal.php`
- ExportController: `yii/controllers/ExportController.php`
- FileExportService: `yii/services/FileExportService.php`
- Editor init JS: `npm/src/js/editor-init.js`
- NoteController (convert-format): `yii/controllers/NoteController.php`

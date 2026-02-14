# Context: import-content-from-server

## Goal
Extend "Load MD" toolbar button with server file import via modal (matching Export Modal pattern).

## Scope
- New: `_import-modal.php` view with IIFE JS
- New: `actionImportServerFile` in NoteController (inject PathService)
- Change: `setupLoadMd` in editor-init.js → opens ImportModal
- Change: main layout includes import modal
- Change: NoteController behaviors add `import-server-file`
- New: Unit tests for actionImportServerFile

## Key References
- Export modal template: `yii/views/layouts/_export-modal.php`
- NoteController: `yii/controllers/NoteController.php`
- editor-init.js: `npm/src/js/editor-init.js`
- DirectorySelector: `npm/src/js/directory-selector.js`
- PathService: `yii/services/PathService.php`
- MarkdownParser: `yii/services/copyformat/MarkdownParser.php`
- QuillDeltaWriter: `yii/services/copyformat/QuillDeltaWriter.php`
- MarkdownDetector: `yii/helpers/MarkdownDetector.php`
- NoteControllerTest: `yii/tests/unit/controllers/NoteControllerTest.php`

# Quill Export Widget — Implementation Context

## Goal
Implement a herbruikbare export widget for Quill editors that allows users to export content to clipboard or file.

## Scope
- **In scope**: Toolbar button, modal dialoog, clipboard export, file export naar project root
- **Out of scope**: Cloud storage, batch export, view pages (alleen form pages)

## Key References
- **Spec**: `.claude/design/feature/quill-export-widget/spec.md`
- **CopyFormatConverter**: `yii/services/CopyFormatConverter.php` — format conversie
- **PathService**: `yii/services/PathService.php` — path validatie en blacklist check
- **CopyType enum**: `yii/common/enums/CopyType.php` — format definities
- **QuillToolbar**: `npm/src/js/editor-init.js` — bestaande toolbar utilities
- **NoteController**: `yii/controllers/NoteController.php` — DI pattern referentie
- **_advanced-search-modal.php**: `yii/views/layouts/_advanced-search-modal.php` — modal pattern
- **note/_form.php**: `yii/views/note/_form.php` — editor integratie pattern

## Target Editors
1. `yii/views/note/_form.php` — Note kan `project_id = null` hebben (alleen Clipboard)
2. `yii/views/context/_form.php` — Context altijd gekoppeld aan project
3. `yii/views/prompt-template/_form.php` — PromptTemplate altijd gekoppeld aan project

## Key Design Decisions
- Modal partial in `yii/views/layouts/_export-modal.php` (volgt `_advanced-search-modal.php` pattern)
- ExportController met `actionToFile()` voor file export
- FileExportService voor logica en validatie
- Clipboard export hergebruikt bestaande `/note/convert-format` endpoint
- Directory listing hergebruikt bestaande `/field/path-list` endpoint

# Context

## Doel
Uitbreiden van de Quill editor "load markdown file" widget met een optie om bestanden van de server te laden, in plaats van alleen van de client.

## Scope
- Bestaande "Load MD" button in Quill toolbar uitbreiden met server-optie
- Aansluiten bij de bestaande "Export content" modal-structuur
- Gebruiken van bestaande PathService en project root_directory infrastructuur

## User Story
We hebben nu in de Quill edit een widget "load markdown file". De gebruiker kan alleen een bestand van de client laden.

1. Breid de functionaliteit uit met een optie om bestanden van de server te laden.
2. Sluit aan bij "Export content" oplossing

## Relevante files
- `npm/src/js/editor-init.js` - Quill toolbar setup, setupLoadMd functie
- `yii/views/layouts/_export-modal.php` - Export modal structuur (referentie)
- `yii/services/FileExportService.php` - Path validatie en project root handling
- `yii/services/PathService.php` - Pad validatie tegen blacklist
- `yii/controllers/NoteController.php` - Import endpoints (actionImportMarkdown)
- `yii/controllers/FieldController.php` - path-list endpoint voor directory autocomplete

## Key References
- DirectorySelector widget voor pad autocomplete
- Project.root_directory voor bestandslocatie
- PathService.resolveRequestedPath voor pad validatie

# Context: Note Index Search

## Goal
Voeg zoekfunctionaliteit toe aan de note/index pagina met tekstzoeken en type filter.

## Scope
- Zoeken in note `name` en `content` (Quill Delta JSON)
- Filter op `type` dropdown
- Integratie met bestaande project-context en show_all toggle
- UX: zoekformulier boven GridView

## Key References
- `yii/models/NoteSearch.php` — Search model, bevat al `name` en `type` safe attrs
- `yii/models/query/NoteQuery.php:74-80` — `searchByTerm()` scope bestaat al
- `yii/controllers/NoteController.php:96-118` — `actionIndex()`
- `yii/views/note/index.php` — View file, moet zoekformulier krijgen
- `common/enums/NoteType.php` — Enum met `labels()` methode

## Architecture Decisions
- Voeg `q` (search term) toe aan `NoteSearch` als nieuw attribute
- Gebruik bestaande `NoteQuery::searchByTerm()` voor gecombineerde name+content search
- Type filter werkt al via `andFilterWhere(['type' => $this->type])`
- Zoekformulier als ActiveForm in view, submit via GET

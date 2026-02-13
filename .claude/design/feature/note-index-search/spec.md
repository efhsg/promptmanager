# Feature: Note Index Search

## Overzicht

Toevoegen van zoekfunctionaliteit aan de note/index pagina, zodat gebruikers snel relevante notes kunnen vinden binnen de huidige projectcontext.

## Probleemstelling

Momenteel heeft de note/index pagina geen zoekfunctionaliteit. Gebruikers met veel notes moeten door paginering bladeren om een specifieke note te vinden. De globale header search zoekt over alle entiteiten heen, maar biedt geen gefocuste notes-only ervaring.

## Gebruikersbehoeften

1. **Snel zoeken in notes** — Gebruiker wil een zoekterm invoeren en direct gefilterde resultaten zien
2. **Filteren op type** — Gebruiker wil notes filteren op type (Note, Summation, Import)
3. **Behoud van context** — Zoeken moet werken binnen de huidige projectcontext ("All Projects" vs specifiek project)
4. **Consistente UX** — De zoekinterface moet aansluiten bij bestaande patterns in de applicatie

## Scope

### In scope
- Zoeken in note `name` (titel)
- Zoeken in note `content` (body, Quill Delta JSON)
- Filteren op `type` (dropdown)
- Filtering werkt samen met bestaande project-context filtering
- Werkt met `show_all` toggle (met/zonder child notes)

### Buiten scope
- Full-text search met ranking/relevantie scoring
- Zoeken in gelinkte project-content
- Geavanceerde query syntax (AND/OR/NOT)
- Real-time autocomplete suggestions

## Acceptatiecriteria

### AC1: Tekstzoeken
- [ ] Gebruiker kan zoeken in note naam via tekstveld
- [ ] Zoeken is case-insensitive
- [ ] Resultaten tonen notes waar zoekterm in naam OF content voorkomt
- [ ] Lege zoekterm toont alle notes (huidige gedrag)

### AC2: Type filter
- [ ] Dropdown met opties: "All Types", "Note", "Summation", "Import" (via `NoteType::labels()`)
- [ ] Filter combineert met tekstzoeken (AND)
- [ ] "All Types" toont alle notes (default)

### AC3: Context-integratie
- [ ] Zoeken respecteert huidige projectcontext
- [ ] Zoeken werkt correct met "Show all" (child notes) toggle
- [ ] Paginering blijft werken met zoekfilters

### AC4: UX
- [ ] Zoekformulier boven de GridView, onder de titel
- [ ] Submit via Enter-toets of Search-knop
- [ ] Reset-knop om filters te wissen
- [ ] Zoektermen behouden bij paginering

## Edge Cases

| Case | Verwacht gedrag |
|------|-----------------|
| Zoekterm in content maar content is Quill Delta JSON | LIKE-query op ruwe JSON; zoekt letterlijk in `insert`-tekst |
| Lege resultaten | "No notes found" melding in GridView |
| Speciale tekens in zoekterm | Yii2 escapet automatisch voor LIKE |
| Zoeken in "All Projects" | Filtert over alle projecten van de user |
| Type filter + project filter + tekst | Alle filters worden gecombineerd (AND) |

## Dependencies

- Bestaand: `NoteSearch` model (`yii/models/NoteSearch.php`)
- Bestaand: `NoteQuery::searchByTerm()` scope (`yii/models/query/NoteQuery.php:74-80`)
- Bestaand: `NoteType` enum met `labels()` (`common/enums/NoteType.php`)
- Bestaand: GridView widget
- Bestaand: Bootstrap 5 form styling + `yii\bootstrap5\ActiveForm`

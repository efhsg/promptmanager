# Design Insights: Note Index Search

## Analyse bestaande patronen

### Globale search (header)
- `QuickSearchService` + `AdvancedSearchService` voor AJAX-gebaseerde search
- Zoekt over meerdere entity types
- Returns JSON, rendered client-side
- `NoteQuery::searchByTerm()` en `searchByKeywords()` zijn al geïmplementeerd

### Andere index pagina's
- `PromptTemplateSearch`, `ContextSearch`, etc. gebruiken `filterModel` pattern
- Maar **geen** van hen heeft een zoekformulier in de view — alleen `filterModel` in GridView
- GridView `filterModel` rendert automatisch input velden in column headers

### NoteSearch huidige staat
- Filtert alleen op `name` en `type`
- Geen content filtering
- Werkt al met projectcontext en show_all toggle

## Ontwerpbeslissingen

### 1. Waarom geen column filters?

De huidige index pagina's gebruiken GridView met klikbare rijen. Column filters (input velden in de header) werken niet goed met clickable rows omdat focus-events conflicteren.

**Oplossing:** Los formulier boven de tabel.

### 2. Waarom niet `searchByTerm()` hergebruiken?

`NoteQuery::searchByTerm()` doet precies wat we nodig hebben, maar:
- Het zit in de Query class, niet in SearchModel
- SearchModel moet de term als attribuut hebben voor form binding
- `andFilterWhere` is eenvoudiger te integreren met het SearchModel pattern

We kunnen beide aanpakken:
- **Optie A:** `$query->searchByTerm($this->name)` aanroepen
- **Optie B:** Inline `andWhere(['or', ...])` in search()

Optie A hergebruikt bestaande code, Optie B is explicieter.

**Beslissing:** Optie A - hergebruik `searchByTerm()` voor consistentie.

### 3. Content zoeken in Quill Delta JSON

Notes slaan content op als Quill Delta JSON:
```json
{"ops":[{"insert":"Hello world\n"}]}
```

Een `LIKE '%hello%'` query vindt "hello" in de JSON string. Dit werkt, maar:
- Geen exact phrase matching (zoekt letterlijk in JSON)
- Kan false positives geven bij JSON structuur (bijv. zoeken op "ops")

**Risico:** Laag. Gebruikers zoeken op inhoud, niet op JSON keys.

**Mitigatie:** Documenteer in help dat zoeken letterlijk is.

### 4. Type filter UX

Opties:
- Dropdown (compact, duidelijk)
- Radio buttons (meer klikken)
- Checkboxes (multi-select, complexer)

**Beslissing:** Dropdown. Consistent met andere filters in de app. Single-select is voldoende voor 3 types.

### 5. Combineren naam + content in één veld

Gebruikers denken niet in "zoek in naam" vs "zoek in content". Ze willen gewoon vinden.

**Beslissing:** Eén zoekveld dat zoekt in naam EN content (OR). Noem het "Search" zonder specifieke scope.

## Niet geïmplementeerd (bewust)

| Feature | Reden |
|---------|-------|
| Live filtering (keyup) | Complexiteit, server load, minder controle |
| Full-text search index | Buiten scope, MySQL FTS zou migratie vereisen |
| Zoeken in child notes apart | Verwarrend, show_all toggle dekt dit |
| Saved searches | Geen vraag naar, complexiteit |

## Consistentie met globale search

De globale advanced search (`_advanced-search-modal.php`) biedt:
- Search mode: phrase vs keywords
- Entity type filtering

Voor de note index search houden we het simpeler:
- Geen mode keuze (default phrase/LIKE)
- Alleen notes (uiteraard)
- Type filter (Note/Summation/Import)

Als gebruikers geavanceerd willen zoeken, kunnen ze de globale advanced search gebruiken.

## Performance overwegingen

- `LIKE '%term%'` query is niet geïndexeerd
- Bij veel notes (1000+) kan dit traag worden
- Huidige paginering (10 per pagina) mitigeert dit

**Toekomstige optimalisatie (indien nodig):**
- Full-text index op `name` en `content`
- Extracted plain-text kolom voor snellere zoek

## Open vragen (opgelost)

| Vraag | Antwoord |
|-------|----------|
| Moeten we project naam ook zoeken? | Nee, projectcontext is al gefilterd |
| Zoeken in parent_id relatie? | Nee, te complex voor nu |
| Moet zoekresultaat naar parent of direct naar note? | Direct naar note (bestaand gedrag) |

## Architecture Review (2026-02-13)

**Score:** 8/10

**Sterke punten:**
- Hergebruik van `NoteQuery::searchByTerm()` — consistent met architecture regel "Query logic belongs in Query classes"
- Custom formulier boven GridView is correcte keuze (clickable rows conflict met column filters)
- Geen onnodige abstracties — volgt "kleinste oplossing die werkt" principe

**Verbeterd in plan:**
- Volledige NoteSearch class toegevoegd met correcte type hints
- Test class structuur toegevoegd (consistent met ContextSearchTest)
- Exacte view locatie gespecificeerd (na regel 63, vóór regel 65)
- `$filterParams` vereenvoudigd naar `$searchParams` met expliciete checks
- Dubbele search() method documentatie verwijderd

## UX Review (2026-02-13)

**Score:** 7/10 → 8/10 (na verbeteringen)

**Geïdentificeerde problemen:**

1. **Accessibility** — `->label(false)` zonder `aria-label` maakt formuliervelden ontoegankelijk voor screenreaders
   - *Opgelost:* `aria-label` toegevoegd aan beide velden

2. **Reset button UX** — Conditoneel verbergen van Reset knop zorgt voor layout shift
   - *Opgelost:* Reset altijd zichtbaar, `disabled` class wanneer geen filters actief

3. **Layout stabiliteit** — `btn-group w-100` met conditonele inhoud zorgt voor ongelijke knopbreedtes
   - *Opgelost:* Vervangen door `d-flex gap-2` voor consistente spacing

4. **Test coverage** — Tests valideerden niet dat niet-matchende records NIET in resultaten zitten
   - *Opgelost:* Sterkere assertions met expliciete ID checks

5. **Fixture dependency** — `NoteFixture` bestond niet
   - *Opgelost:* Volledige fixture + data file toegevoegd aan plan

**Toegankelijkheidschecklist:**
- [x] Labels of aria-labels op alle form controls
- [x] Reset button altijd zichtbaar (geen layout shift)
- [x] Keyboard navigatie via standard form controls
- [x] Focus management via Bootstrap defaults

## Code Review (2026-02-13)

**Score:** 8/10 (na verbeteringen)

**Verbeteringen aangebracht:**

1. **PHPDoc property annotation** — `@property string|null $searchTerm` toegevoegd aan NoteSearch class docblock voor IDE autocomplete en documentatie

2. **Fixture data consistentie** — Fixture data gecorrigeerd:
   - Verwijderd: user_id 101 en project_id 3 (bestaan niet in bestaande fixtures)
   - Aangepast: Gebruikt alleen user_id 100/1 en project_id 1/2 die in `user.php` en `projects.php` fixtures bestaan
   - Toegevoegd: Associatieve keys (`note1`, `note2`, etc.) voor consistentie met andere fixtures

3. **Test assertions** — `assertSame(N, count($models))` vervangen door `assertCount(N, $models)` voor expressievere assertions

4. **Test logic** — `testSearchExcludesOtherUsers` aangepast om te verifiëren dat user 100 geen notes van user 1 ziet (i.p.v. niet-bestaande user 101)

5. **Test method** — `getTotalCount()` vervangen door `getModels()` + `assertCount()` voor directere verificatie

## Final Review (2026-02-13)

**Score:** 8/10

**Laatste verbeteringen aangebracht:**

1. **Implementatie stappen** — Toegevoegd aan plan.md voor duidelijke volgorde

2. **View instructies verduidelijkt** — Exacte regels voor verwijderen en import locatie gespecificeerd

3. **Reset button accessibility** — Vervangen door `Html::tag('span')` met `aria-disabled="true"` voor disabled state (correcte HTML)

4. **Test assertions verbeterd** — `assertContains` met integers vervangen door `array_column` + `sort` + `assertSame` voor deterministische tests

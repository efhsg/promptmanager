# Feature: Notes Evolution

> ScratchPad → Note: formalisering, typering en zelfverwijzing

## Status

`DRAFT` — wacht op akkoord voordat implementatie start

---

## 1. Achtergrond

De `scratch_pad` entiteit is gegroeid van een eenvoudig kladblok naar een centraal tekstblok met meerdere functies: notities, AI-responses, importbestanden, YouTube-transcripten. Deze evolutie willen we formaliseren.

### Huidige situatie

| Aspect | Nu |
|--------|-----|
| Tabelnaam | `scratch_pad` |
| Model | `ScratchPad` |
| Kolommen | `id`, `user_id`, `project_id`, `name`, `content`, `response`, `created_at`, `updated_at` |
| Controller-route | `/scratch-pad/*` |
| RBAC | `createScratchPad`, `viewScratchPad`, `updateScratchPad`, `deleteScratchPad` |
| `response`-veld | Quill Delta JSON — slaat AI-antwoord op als los tekstblok |

### Gewenste situatie

1. **Hernoem** `scratch_pad` → `note` (tabel, model, controller, routes, RBAC, views, services, tests)
2. **Voeg `type` toe** — `NoteType` enum die eenvoudig uit te breiden is
3. **Zelfverwijzing** — `parent_id` foreign key naar `note.id` zodat notes aan elkaar gekoppeld worden
4. **Vervang `response`-veld** — wordt een gekoppelde note van type `RESPONSE` (via `parent_id`)

---

## 2. Requirements

### R1 — Rename ScratchPad → Note

**Wat:** Alle interne en externe verwijzingen naar "scratch pad" worden "note".

| Laag | Van | Naar |
|------|-----|------|
| Database tabel | `scratch_pad` | `note` |
| Model klasse | `ScratchPad` | `Note` |
| Query klasse | `ScratchPadQuery` | `NoteQuery` |
| Search model | `ScratchPadSearch` | `NoteSearch` |
| Controller | `ScratchPadController` | `NoteController` |
| API controller | `api/ScratchPadController` | `api/NoteController` |
| RBAC rule | `ScratchPadOwnerRule` | `NoteOwnerRule` |
| RBAC permissions | `*ScratchPad` | `*Note` |
| RBAC config entity key | `'scratchPad'` | `'note'` |
| View directory | `views/scratch-pad/` | `views/note/` |
| URL routes | `/scratch-pad/*` | `/note/*` |
| Menu label | "Scratch Pads" | "Notes" |
| Sync entity key | `scratch_pad` | `note` |
| Search type constant | `TYPE_SCRATCH_PADS` | `TYPE_NOTES` |
| Search result type value | `'scratchPad'` | `'note'` |
| QuickSearch result key | `'scratchPads'` | `'notes'` |

**Acceptatiecriteria:**
- AC1.1: Alle bestaande data blijft behouden (migratie hernoemt tabel + kolommen)
- AC1.2: Oude URL's `/scratch-pad/*` worden niet automatisch geredirect (clean break)
- AC1.3: RBAC-migratie past permission-namen aan via `authManager` API (niet raw SQL) en roept `EntityPermissionService::invalidatePermissionCache()` aan
- AC1.4: Alle unit-tests slagen na hernoemen
- AC1.5: Sync (EntityDefinitions) werkt met nieuwe naamgeving
- AC1.6: Frontend search rendering toont het juiste type-label voor notes

### R2 — NoteType enum

**Wat:** Een `type` kolom die het doel van de note classificeert.

```php
enum NoteType: string
{
    case NOTE = 'note';           // Standaard — vrije notitie (was: scratch pad)
    case RESPONSE = 'response';   // AI-response, gekoppeld aan parent
    case IMPORT = 'import';       // Geimporteerd bestand (markdown, YouTube)
}
```

**Database:**
- Kolom: `type VARCHAR(50) NOT NULL DEFAULT 'note'`
- Index: Composite `(user_id, type)` voor filtering op type

**Acceptatiecriteria:**
- AC2.1: Alle bestaande records krijgen `type = 'note'` (default)
- AC2.2: Enum is uitbreidbaar — nieuwe case toevoegen vereist geen migratie (VARCHAR)
- AC2.3: `NoteType::values()` wordt gebruikt in model validation rule
- AC2.4: `NoteType::labels()` wordt gebruikt in views voor dropdowns/filters
- AC2.5: Zoekfunctie (NoteSearch, QuickSearch, AdvancedSearch) kan filteren op type
- AC2.6: Note model bevat validation rule `['type', 'in', 'range' => NoteType::values()]`
- AC2.7: `actionSave()` valideert `type` via `NoteType::tryFrom()` — ongeldige waarden worden afgewezen met foutmelding

### R3 — Zelfverwijzing (parent_id)

**Wat:** Notes kunnen aan elkaar gekoppeld worden via een `parent_id` foreign key.

**Database:**
- Kolom: `parent_id INT NULL` → foreign key naar `note.id` (ON DELETE SET NULL)
- Index op `parent_id`

**Model relaties:**
- `Note::getParent(): ActiveQuery` — hasOne naar parent note
- `Note::getChildren(): ActiveQuery` — hasMany naar child notes

**Query methodes:**
- `NoteQuery::forParent(?int $parentId)` — filter op parent
- `NoteQuery::topLevel()` — alleen notes zonder parent (`parent_id IS NULL`)
- `NoteQuery::withChildren()` — eager load children

**Acceptatiecriteria:**
- AC3.1: Een note kan exact 0 of 1 parent hebben
- AC3.2: Een note kan 0..n children hebben
- AC3.3: Bij verwijderen parent worden children NIET verwijderd (ON DELETE SET NULL)
- AC3.4: Circulaire verwijzingen worden niet op DB-niveau afgedwongen (single level)
- AC3.5: View toont gekoppelde children onder de parent note
- AC3.6: Bij aanmaken/updaten via `actionSave()` wordt `parent_id` gevalideerd op eigenaarschap (`user_id`) en bestaan via `Note::find()->forUser($userId)->andWhere(['id' => $parentId])->exists()`
- AC3.7: Child notes erven `project_id` van de parent bij aanmaak. Wijziging achteraf is toegestaan.

### R4 — Response-veld migratie

**Wat:** Het huidige `response`-veld wordt vervangen door een gekoppelde note van type `RESPONSE`.

**Migratielogica:**
1. Voor elke note met een niet-lege `response`:
   - Maak een nieuwe note aan met:
     - `user_id` = originele note's `user_id`
     - `project_id` = originele note's `project_id`
     - `name` = `"Response: {originele note naam}"`
     - `content` = waarde van origineel `response`-veld
     - `type` = `'response'`
     - `parent_id` = originele note's `id`
     - `created_at` / `updated_at` = originele note's timestamps
2. Na verificatie: verwijder kolom `response` (aparte migratie)

**"Niet-leeg" definitie:** Een `response` is niet-leeg als het:
- Niet NULL is
- Niet een lege string is
- Niet de Quill "empty delta" is: `{"ops":[{"insert":"\n"}]}`

**Acceptatiecriteria:**
- AC4.1: Alle bestaande responses worden omgezet naar child notes met type RESPONSE
- AC4.2: De `response` kolom wordt verwijderd na succesvolle datamigratie
- AC4.3: Views tonen response-children in plaats van het oude response-veld
- AC4.4: De AJAX save-endpoint accepteert response als aparte note-save
- AC4.5: Een response-child wordt aangemaakt via een apart save-request met `type='response'` en `parent_id` — er is geen "direct meegeven" flow in het save-endpoint
- AC4.6: Lege responses worden NIET gemigreerd (geen lege child notes)

---

## 3. Edge cases

### 3.1 Lege response bij migratie
**Situatie:** Note heeft `response` maar het bevat alleen `{"ops":[{"insert":"\n"}]}`.
**Gedrag:** Wordt NIET gemigreerd als child note. Lege delta = geen content.

### 3.2 Note verwijderen met children
**Situatie:** Gebruiker verwijdert een parent note die response-children heeft.
**Gedrag:** ON DELETE SET NULL — children worden orphans (parent_id = NULL, type blijft 'response').
**Overweging:** Moeten orphan response-notes zichtbaar zijn in de lijst? → Ja, maar met indicator dat de parent ontbreekt. Orphan notes verschijnen automatisch in de top-level lijst doordat `topLevel()` filtert op `parent_id IS NULL`.

### 3.3 Sync (EntityDefinitions)
**Situatie:** Entity sync exporteert/importeert notes.
**Gedrag:**
- Entity key wordt `note`
- `type` en `parent_id` worden toegevoegd aan sync columns
- `parent_id` moet als foreign key naar `note` gedefinieerd worden: `'parent_id' => 'note'`
- Sync volgorde: note moet voor zichzelf staan (parent notes eerst) → **oplossing: sorteer op `parent_id IS NULL DESC`** zodat parent notes eerst worden geimporteerd. De bestaande FK-resolutie in de sync engine handelt de parent_id mapping af mits de import-volgorde correct is.

**Opmerking:** Het huidige sync-schema (`EntityDefinitions`) bevat geen `response` kolom — die was al uitgesloten van sync. Dus alleen `type` en `parent_id` toevoegen is voldoende.

### 3.4 API backwards compatibility
**Situatie:** REST API endpoint `POST api/scratch-pad` wordt `POST api/note`.
**Gedrag:** Oude endpoint stopt met werken. API-consumers moeten bijgewerkt worden.
**Overweging:** De API wordt alleen intern gebruikt (CLI), dus geen BC-laag nodig. Verifieer dat er geen externe consumers zijn die de bearer auth gebruiken.

### 3.5 YouTube/Markdown import
**Situatie:** Import-acties maken notes aan.
**Gedrag:** Geimporteerde notes krijgen `type = 'import'` in plaats van default 'note'.
**Overweging:** Bestaande geimporteerde notes (voor migratie) behouden `type = 'note'` — we herclassificeren niet retroactief.

### 3.6 Zoeken in children
**Situatie:** QuickSearch/AdvancedSearch zoekt in notes.
**Gedrag:** Alle notes (incl. children) worden doorzocht. In resultaten wordt de note zelf weergegeven met de correcte URL. Wanneer een child note wordt gevonden, toont het resultaat de child's naam en linkt naar de parent's view-pagina (waar de child zichtbaar is als card). Dit is de eenvoudigste aanpak voor v1.

**Implementatie:** De bestaande `searchByTerm()` in NoteQuery doorzoekt alle notes. In het search resultaat wordt het `url` veld aangepast: als de note een `parent_id` heeft, link naar de parent view (`/note/view?id={parent_id}`), anders naar de note zelf.

### 3.7 Claude-integratie (fetch-content)
**Situatie:** Claude launch-button haalt content op via `fetch-content`.
**Huidig gedrag:** `actionFetchContent` retourneert alleen `content` — het `response`-veld wordt niet meegestuurd. De caller (`scratch-pad/view.php` JS) slaat het resultaat op in `sessionStorage['claudePromptContent']` en navigeert naar `/claude/index`.
**Nieuw gedrag:** `actionFetchContent` retourneert parent content + children content samengevoegd als een enkel Quill Delta JSON document. Children worden in `created_at` volgorde na de parent gevoegd, gescheiden door een dubbele newline insert (`{"insert":"\n\n"}`). De response-structuur blijft: `{ success: true, content: "...", projectId: N }`.

**Merge-algoritme (in `NoteService::fetchMergedContent()`):**
1. Neem de parent note's `content` — decode naar `ops` array
2. Verwijder de trailing `{"insert":"\n"}` (Quill standaard trailing newline) van de parent ops
3. Voor elk child (gesorteerd op `created_at ASC`):
   a. Voeg separator toe: `{"insert":"\n\n"}`
   b. Decode child `content` naar `ops` array
   c. Als het NIET het laatste child is: verwijder trailing `{"insert":"\n"}`
   d. Concateneer child ops aan het resultaat
4. Encode terug naar JSON: `{"ops": [... merged ops ...]}`
5. Als er geen children zijn, retourneer het parent content ongewijzigd

**View.php Claude launch-buttons (na migratie):**
De huidige `view.php` heeft twee Claude launch-buttons: content (`data-delta="content"`) en response (`data-delta="response"`). Na migratie:
- De response launch-button verdwijnt
- De content launch-button stuurt de parent content (ongewijzigd)
- Een nieuwe "Launch Claude (all)" button roept `actionFetchContent` aan via AJAX om merged content (parent + children) op te halen en slaat dit op in `sessionStorage['claudePromptContent']`
- Per child note card: optionele "Launch Claude" button die alleen die child's content stuurt

### 3.8 RBAC bij child notes
**Situatie:** Een child note heeft dezelfde `user_id` als de parent.
**Gedrag:** Eigenaarschap wordt per note gecontroleerd via `user_id` — geen overerving via parent.

### 3.9 "Save As" met children
**Situatie:** Gebruiker kopieert een note met "Save As".
**Gedrag:** Alleen de parent wordt gekopieerd, niet de children. De kopie heeft geen parent_id.

### 3.10 Import flow type-toewijzing
**Situatie:** YouTube of Markdown import maakt een nieuwe note aan.
**Gedrag:** `NoteController::actionImportYoutube()` zet `type = NoteType::IMPORT` op het model voor save. De `actionSave()` endpoint accepteert een optioneel `type`-veld in het request, zodat de save-modal het type kan meegeven wanneer relevant. Als geen type wordt meegegeven, geldt de default `'note'`.

**Let op:** `actionImportMarkdown()` retourneert alleen de geconverteerde Delta JSON aan de client (geen model-save), dus type-toewijzing vindt daar niet plaats — het type wordt bepaald wanneer de gebruiker opslaat. Alleen `actionImportYoutube()` maakt direct een model aan.

**Let op:** Import-acties retourneren JSON (geen redirect) — de client-side JS verwerkt het resultaat. URL-verwijzingen in de import endpoints hoeven dus niet aangepast te worden buiten de controller-hernoaming zelf.

### 3.11 Save endpoint wijzigingen
**Situatie:** De huidige `actionSave()` accepteert `{ id, name, content, response, project_id }` als raw JSON POST body. Bij `id=null` wordt een nieuw record aangemaakt, bij bestaand `id` een update (met ownership-check).
**Gedrag na migratie:**
- `actionSave()` accepteert: `{ id, name, content, project_id, type (optioneel), parent_id (optioneel) }`
- Het `response`-veld verdwijnt uit het request
- Om een response-child toe te voegen wordt een apart save-request gedaan met `type = 'response'` en `parent_id` van de parent note
- De create-view stuurt geen response-data meer mee in het initiele save-request
- Bestaande flow (create vs update verschil) blijft behouden: create-view gebruikt AJAX `actionSave()`, update-view gebruikt ActiveForm met `_form.php`

**Validatie in `actionSave()`:**
- `type`: Valideer via `NoteType::tryFrom($type)`. Ongeldige waarden → foutmelding. Geen type meegegeven → default `'note'`.
- `parent_id`: Valideer via `Note::find()->forUser($userId)->andWhere(['id' => $parentId])->exists()`. Niet-bestaand of niet-owned → foutmelding. Bij aanmaak child: `project_id` wordt overgenomen van parent tenzij expliciet meegegeven.
- De save-logica wordt gedelegeerd aan `NoteService::saveNote()` conform het project-patroon (logica in services, niet in controllers).

**NoteService class definitie:**
```php
class NoteService
{
    // Geen constructor dependencies in v1
    // Extend Component niet (vergelijkbaar met FieldService)
    // Voeg DI toe wanneer dependencies nodig worden
}
```

**NoteService method signatures:**
```php
/**
 * @throws RuntimeException when validation fails
 */
public function saveNote(array $data, int $userId): Note

public function deleteNote(Note $model): bool

/**
 * Merges parent + children content into single Quill Delta JSON.
 */
public function fetchMergedContent(Note $note): string
```

**Architectuurkeuze:** `saveNote()` accepteert een associative array i.p.v. een DTO. Dit is consistent met hoe de huidige `actionSave()` al werkt (raw JSON POST body → losse variabelen). Een DTO is overengineering voor v1 met 5-6 velden. Als het datamodel groeit, kan dit later gerefactord worden.

### 3.12 Deprecated `actionClaude()` redirect
**Situatie:** De huidige controller heeft een deprecated `actionClaude()` die redirects naar `/claude/index`.
**Gedrag:** Wordt mee hernoemd naar NoteController. De actie blijft functioneel identiek, alleen de interne route verandert. RBAC action map bevat al `'claude' => 'viewScratchPad'` → wordt `'claude' => 'viewNote'`.

### 3.13 `response-summary` use case in ClaudeQuickHandler
**Situatie:** De handler bevat een `'response-summary'` use case naast `'scratch-pad-name'`.
**Besluit:** De `response-summary` use case is niet direct gekoppeld aan het ScratchPad response-veld — het is een zelfstandige AI-completion use case. De workdir (`.claude/workdirs/response-summary/`) is onafhankelijk van de scratch-pad naamgeving en hoeft **niet** hernoemd te worden. De use case key `'response-summary'` in ClaudeQuickHandler blijft ongewijzigd.

### 3.14 Claude/index.php save-flow na migratie
**Situatie:** De huidige `views/claude/index.php` heeft een two-step save dialog (`saveDialogSelectModal` → `saveDialogSaveModal`) die geselecteerde berichten opslaat via `POST /scratch-pad/save` met response-data in het `response`-veld.
**Gedrag na migratie:**
1. Save-endpoint URL: `POST /scratch-pad/save` → `POST /note/save`
2. Eerste save: `POST /note/save` met `{ name, content, project_id }` — maakt parent note aan, retourneert `{ success, id }`
3. Tweede save (als er response-berichten zijn geselecteerd): `POST /note/save` met `{ name: "Response: {name}", content: {response_content}, type: "response", parent_id: {parent_id_uit_stap_1} }`
4. Error handling: als de eerste save faalt, wordt de tweede niet uitgevoerd. Als de tweede faalt, bestaat de parent note al maar zonder response-child — de gebruiker kan de response later alsnog toevoegen.
5. Modal titel: "Save Scratch Pad" → "Save Note" (in `saveDialogSaveModal`)
6. Verifieer of `saveDialogSelectModal` tekst bevat die hernoemd moet worden

**Opmerking:** De `sessionStorage` key `claudePromptContent` bevat geen scratch-pad referentie en hoeft **niet** hernoemd te worden.

### 3.15 Editor IDs in create vs update views
**Situatie:** De `create.php` en `_form.php` (update) gebruiken verschillende HTML ID-patronen voor editors.
**Huidig gedrag:**
- `create.php` gebruikt: `#editor` (content), `#response-editor` (response) — zonder `scratch-pad-` prefix
- `_form.php` (update) gebruikt: `#scratch-pad-editor`, `#scratch-pad-response-editor` — met prefix

**Gedrag na migratie:**
- `create.php`: `#editor` (geen hernoaming nodig — geen prefix), response-editor verdwijnt
- `_form.php`: `#scratch-pad-editor` → `#note-editor`, `#scratch-pad-response-editor` verdwijnt

De ID-mapping in §7 "JavaScript — client-side keys" geldt alleen voor de `_form.php` IDs, niet voor de `create.php` IDs.

---

## 4. UI-wijzigingen

### 4.1 Navigatiemenu
- "Scratch Pads" → "Notes"
- Nav ID `#nav-scratch-pads` → `#nav-notes`
- JS-variabele `scratchPadsLink` → `notesLink`

### 4.2 Index-pagina
- Titel: "Saved Scratch Pads" → "Notes"
- Knop: "New Scratch Pad" → "New Note"
- Lijst toont kolom `type` met badge (Note / Response / Import)
- Filter op type (dropdown of tabs)
- Response-children worden standaard NIET getoond in de hoofdlijst (alleen top-level)
- Toggle "Show all" om ook children te tonen (via query parameter `show_all=1`)

### 4.3 Create-pagina
- Titel: "Scratch Pad" → "Note"
- Het response-accordion-paneel verdwijnt uit het formulier
- Er komt een "Children" sectie onder de content die gekoppelde notes toont (alleen bij update, niet bij create)

### 4.4 Update/View-pagina
- Het response-accordion verdwijnt
- Gekoppelde children worden als cards onder de content getoond
- Knop "Add Response" maakt een nieuwe child note met type RESPONSE
- Knop "Add Note" maakt een nieuwe child note met type NOTE
- Claude launch-buttons: zie §3.7 voor de aangepaste flow

### 4.5 Save Modal
- "Save Scratch Pad" → "Save Note"
- Alle labels en placeholders bijwerken
- HTML ID's hernoemen: `scratch-pad-name` → `note-name`, `scratch-pad-project` → `note-project`, etc.

---

## 5. Datamodel (na migratie)

### Tabel: `note`

| Kolom | Type | Nullable | Default | Opmerking |
|-------|------|----------|---------|-----------|
| `id` | INT PK AI | Nee | — | |
| `user_id` | INT | Nee | — | FK → `user.id` CASCADE |
| `project_id` | INT | Ja | NULL | FK → `project.id` CASCADE |
| `parent_id` | INT | Ja | NULL | FK → `note.id` SET NULL |
| `name` | VARCHAR(255) | Nee | — | |
| `type` | VARCHAR(50) | Nee | `'note'` | Zie NoteType enum |
| `content` | LONGTEXT | Ja | NULL | Quill Delta JSON |
| `created_at` | INT | Nee | — | Unix timestamp via TimestampTrait::time() |
| `updated_at` | INT | Nee | — | Unix timestamp via TimestampTrait::time() |

### Indexes

- `idx_note_user_id_project_id` (user_id, project_id)
- `idx_note_user_id_type` (user_id, type)
- `idx_note_parent_id` (parent_id)

### Foreign keys

- `fk_note_user` → `user.id` ON DELETE CASCADE
- `fk_note_project` → `project.id` ON DELETE CASCADE
- `fk_note_parent` → `note.id` ON DELETE SET NULL

**Aanname:** `$auth->update()` in Yii2's `DbManager` werkt via `auth_item` tabelupdate. De `auth_item_child` koppeltabel heeft FK's naar `auth_item.name` met CASCADE, waardoor permission-hernoaming automatisch doorpropageert naar role-permission assignments. Dit is geverifieerd als standaard Yii2 RBAC-gedrag.

---

## 6. Migratievolgorde

De migratie wordt opgesplitst in stappen voor veiligheid en reverseerbaarheid.

Alle migraties gebruiken `{{%table_name}}` syntax voor table prefix support en `safeUp()`/`safeDown()` voor transacties, conform project-standaard.

**Opmerking:** Migraties gebruiken string literals (`'response'`, `'note'`), niet enum classes of model constants. Migraties mogen geen dependency op applicatiecode hebben — dit garandeert dat ze uitvoerbaar blijven ongeacht toekomstige codewijzigingen.

### Migratie 1: Hernoem tabel + voeg kolommen toe

1. Drop bestaande foreign keys:
   - `fk_scratch_pad_user`
   - `fk_scratch_pad_project`
2. Drop bestaande index:
   - `idx_scratch_pad_user_project`
3. Hernoem tabel:
   - `$this->renameTable('{{%scratch_pad}}', '{{%note}}')`
4. Recreate foreign keys met nieuwe namen:
   - `fk_note_user`: `note.user_id` → `user.id` ON DELETE CASCADE
   - `fk_note_project`: `note.project_id` → `project.id` ON DELETE CASCADE
5. Voeg kolom toe:
   - `type VARCHAR(50) NOT NULL DEFAULT 'note'`
6. Voeg kolom toe:
   - `parent_id INT NULL`
7. Voeg foreign key toe:
   - `fk_note_parent`: `note.parent_id` → `note.id` ON DELETE SET NULL
8. Voeg indexes toe:
   - `idx_note_user_id_project_id` (user_id, project_id)
   - `idx_note_user_id_type` (user_id, type)
   - `idx_note_parent_id` (parent_id)

**safeDown():** Omgekeerde volgorde:
1. Drop indexes: `idx_note_parent_id`, `idx_note_user_id_type`, `idx_note_user_id_project_id`
2. Drop FK: `fk_note_parent`
3. Drop kolommen: `parent_id`, `type`
4. Drop FK's: `fk_note_project`, `fk_note_user`
5. Hernoem tabel: `$this->renameTable('{{%note}}', '{{%scratch_pad}}')`
6. Recreate originele FK's:
   - `fk_scratch_pad_user`: `scratch_pad.user_id` → `user.id` ON DELETE CASCADE
   - `fk_scratch_pad_project`: `scratch_pad.project_id` → `project.id` ON DELETE CASCADE
7. Recreate originele index:
   - `idx_scratch_pad_user_project` (user_id, project_id)

### Migratie 2a: Migreer response-data

**Implementatiekeuze:** PHP-loop migratie (niet pure SQL). Dit maakt de empty-delta check robuuster via JSON decode en voorkomt problemen met whitespace-variaties in de opgeslagen Quill Delta JSON.

**safeUp() logica:**
```php
$rows = (new Query())
    ->select(['id', 'user_id', 'project_id', 'name', 'response', 'created_at', 'updated_at'])
    ->from('{{%note}}')
    ->where(['NOT', ['response' => null]])
    ->andWhere(['NOT', ['response' => '']])
    ->all();

foreach ($rows as $row) {
    if ($this->isEmptyDelta($row['response'])) {
        continue;
    }
    $this->insert('{{%note}}', [
        'user_id' => $row['user_id'],
        'project_id' => $row['project_id'],
        'name' => 'Response: ' . $row['name'],
        'content' => $row['response'],
        'type' => 'response',
        'parent_id' => $row['id'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ]);
}
```

**`isEmptyDelta()` helper (private method in migratie):**
```php
private function isEmptyDelta(string $value): bool
{
    $decoded = json_decode($value, true);
    if (!is_array($decoded) || !isset($decoded['ops'])) {
        return false;
    }
    $ops = $decoded['ops'];
    return count($ops) === 1
        && isset($ops[0]['insert'])
        && $ops[0]['insert'] === "\n";
}
```

**Verificatie na loop:**
```php
// Count notes met niet-lege response (excl. empty deltas — al gefilterd in loop)
// Count child notes met type='response'
// Log beide counts voor handmatige verificatie
```

**safeDown():** Kopieer `content` van response-children terug naar parent `response`-kolom, daarna DELETE child notes.

**safeDown() guard:** Controleer of de `response`-kolom bestaat voordat data wordt teruggekopieerd. Als migratie 2b al is uitgevoerd (kolom verwijderd), geeft de down een duidelijke foutmelding:
```php
$schema = $this->db->getTableSchema('{{%note}}');
if ($schema->getColumn('response') === null) {
    throw new RuntimeException(
        'Cannot reverse: response column does not exist. Run down for migration 2b first.'
    );
}
```

### Migratie 2b: Verwijder response-kolom

1. `$this->dropColumn('{{%note}}', 'response')`

> Gesplitst van 2a zodat data-migratie apart geverifieerd kan worden voor onherstelbaar schema-verlies.

**safeDown():** `$this->addColumn('{{%note}}', 'response', $this->text()->null())`

### Migratie 3: RBAC hernoemen

**Implementatiekeuze:** Gebruik `Yii::$app->authManager` API (niet raw SQL), conform bestaand patroon in `m250715_120000_add_generate_prompt_permission.php`. Dit respecteert Yii's RBAC cache-mechanisme.

**safeUp() logica:**
1. Guard: `$auth = Yii::$app->authManager` — verify `instanceof DbManager`
2. Rename permissions via `$auth->update()`:
   - `createScratchPad` → `createNote`
   - `viewScratchPad` → `viewNote`
   - `updateScratchPad` → `updateNote`
   - `deleteScratchPad` → `deleteNote`

   Per permission: `$perm = $auth->getPermission('createScratchPad')` → `$perm->description = 'Create a Note'` → `$auth->update('createScratchPad', $newPerm)` met nieuwe naam
3. Update rule: `$rule = $auth->getRule('isScratchPadOwner')` → `$auth->remove($rule)` → add new `NoteOwnerRule` → update permissions die de oude rule gebruikten
4. Role-permission assignments worden automatisch bijgewerkt door `$auth->update()` (cascading via `auth_item_child` FK's naar `auth_item.name` met CASCADE)
5. Invalidate cache: `EntityPermissionService::invalidatePermissionCache()`

**safeDown():** Alle renames omkeren met dezelfde `$auth->update()` aanpak + `EntityPermissionService::invalidatePermissionCache()`.

---

## 7. Impactanalyse — bestanden

### PHP (model/service/controller)

| Bestand | Actie |
|---------|-------|
| `models/ScratchPad.php` | Hernoem → `Note.php`, pas class/tabel/relaties aan, voeg `getParent()`, `getChildren()` toe |
| `models/ScratchPadSearch.php` | Hernoem → `NoteSearch.php`, voeg type-filter toe, voeg `topLevel()` als default scope toe (met `showAll` toggle via query parameter `show_all`), conform §4.2. `search()` krijgt extra parameter `bool $showChildren = false`. |
| `models/query/ScratchPadQuery.php` | Hernoem → `NoteQuery.php`, voeg `forParent()`, `topLevel()`, `withChildren()` toe |
| `controllers/ScratchPadController.php` | Hernoem → `NoteController.php`, pas actionSave() aan (verwijder response, voeg type/parent_id toe, delegeer naar NoteService), pas actionFetchContent() aan (delegeer naar NoteService::fetchMergedContent()), behoud deprecated actionClaude() redirect |
| `controllers/api/ScratchPadController.php` | Hernoem → `api/NoteController.php` |
| `rbac/ScratchPadOwnerRule.php` | Hernoem → `NoteOwnerRule.php`, pas `$name` aan naar `'isNoteOwner'` |
| `config/rbac.php` | Entity key `'scratchPad'` → `'note'`, permission-namen hernoemen, rule class `ScratchPadOwnerRule` → `NoteOwnerRule`, action-permission map `claude`/`fetchContent` behouden |
| `config/main.php` | API route `'POST api/scratch-pad' => 'api/scratch-pad/create'` → `'POST api/note' => 'api/note/create'` |
| `services/QuickSearchService.php` | Method `searchScratchPads` → `searchNotes`, type key `'scratchPad'` → `'note'`, result key `'scratchPads'` → `'notes'`. URL `/scratch-pad/view` → `/note/view`. Child notes linken naar parent view (als `parent_id` gezet). |
| `services/AdvancedSearchService.php` | Constant `TYPE_SCRATCH_PADS` → `TYPE_NOTES`, method hernoemen, label `'Scratch Pads'` → `'Notes'`. URL en child-link logica idem QuickSearchService. |
| `services/sync/EntityDefinitions.php` | Entity key `'scratch_pad'` → `'note'`, kolommen `type` + `parent_id` toevoegen, FK `'parent_id' => 'note'` toevoegen, sync order entry updaten |
| `services/projectload/EntityConfig.php` | Alle referenties `'scratch_pad'` → `'note'` in: `COLUMN_OVERRIDES`, `getEntities()`, `getInsertOrder()`, `getAutoIncrementEntities()`, `getListColumns()` (label `'SP'` → `'Nt'`). FK `'parent_id' => 'note'` toevoegen aan entity definitie. |
| `services/projectload/EntityLoader.php` | Tabel referentie `scratch_pad` → `note` in `countLocalEntities()` query |
| `components/ProjectUrlManager.php` | `'scratch-pad'` → `'note'` in `PROJECT_SCOPED_PREFIXES` |
| `handlers/ClaudeQuickHandler.php` | Use-case key `'scratch-pad-name'` → `'note-name'`, workdir `'scratch-pad-name'` → `'note-name'` (koppeling met `.claude/workdirs/` hernoaming in §7 Documentatie). Use case `'response-summary'` blijft ongewijzigd (besluit §3.13). |
| `services/NoteService.php` | **NIEUW** — `saveNote(array $data, int $userId): Note`, `deleteNote(Note $model): bool`, `fetchMergedContent(Note $note): string`. Delegeert ownership-validatie aan query scopes. Controller delegeert naar service conform project-patroon (ContextService, FieldService). Class extend Component niet (volgt FieldService patroon). |

### Views — in `views/scratch-pad/` (8 bestanden → `views/note/`)

| Bestand | Actie |
|---------|-------|
| `index.php` | Verplaats, pas titels/labels/CSS-classes aan |
| `create.php` | Verplaats, verwijder response-accordion, pas save-modal labels aan. **Let op:** editor IDs zijn `#editor` (zonder prefix) — geen hernoaming nodig. |
| `update.php` | Verplaats, pas referenties aan |
| `_form.php` | Verplaats, verwijder response-editor, voeg children-sectie toe. **Let op:** Save As gebruikt `document.getElementById('scratchpad-project_id')` — dit wordt het ActiveForm-gegenereerde ID `note-project_id`. Editor IDs: `#scratch-pad-editor` → `#note-editor`. |
| `view.php` | Verplaats, vervang response-viewer door children-cards. Accordion ID `scratchPadViewAccordion` → `noteViewAccordion`. Claude launch-buttons aanpassen conform §3.7. |
| `delete-confirm.php` | Verplaats, pas labels/breadcrumbs aan |
| `_import-modal.php` | Verplaats, pas URL's en HTML ID's aan |
| `_youtube-import-modal.php` | Verplaats, pas URL's aan |

### Views — buiten `views/scratch-pad/` (URL-referenties updaten)

| Bestand | Wijziging |
|---------|-----------|
| `views/layouts/main.php` | Menu label "Scratch Pads" → "Notes", route `/scratch-pad/index` → `/note/index`, nav-ID `#nav-scratch-pads` → `#nav-notes`, JS-variabele `scratchPadsLink` → `notesLink` |
| `views/project/_form.php` | URL's `scratch-pad/import-text` + `import-markdown` → `note/...`, help-tekst "scratch pads" → "notes" |
| `views/context/_form.php` | URL's `scratch-pad/import-text` + `import-markdown` → `note/...` |
| `views/field/_form.php` | URL's `scratch-pad/import-text` + `import-markdown` → `note/...` |
| `views/prompt-template/_form.php` | URL's `scratch-pad/import-text` + `import-markdown` → `note/...` |
| `views/prompt-instance/_form.php` | URL's `scratch-pad/import-text` + `import-markdown` → `note/...` |
| `views/prompt-instance/update.php` | URL's `scratch-pad/import-text` + `import-markdown` → `note/...` |
| `views/claude/index.php` | URL `scratch-pad/view` → `note/view` (`$viewUrlTemplate`). Save-endpoint `POST /scratch-pad/save` → `POST /note/save`. Save-flow aanpassen: twee saves (parent + response-child) conform §3.14. Modal-titel "Save Scratch Pad" → "Save Note" in `saveDialogSaveModal`. `sessionStorage` key `claudePromptContent` blijft ongewijzigd. |

### JavaScript — search rendering

| Bestand | Actie |
|---------|-------|
| `yii/web/js/quick-search.js` | Group label `scratchPads: "Scratch Pads"` → `notes: "Notes"` |
| `yii/web/js/advanced-search.js` | Group label `scratchPads: "Scratch Pads"` → `notes: "Notes"` |
| `views/layouts/_advanced-search-modal.php` | Dynamisch via `AdvancedSearchService::typeLabels()` — geen handmatige wijziging nodig |

### JavaScript — editor/toolbar

| Bestand | Actie |
|---------|-------|
| `npm/src/js/editor-init.js` | URL-constanten `DEFAULT_IMPORT_TEXT_URL`, `DEFAULT_IMPORT_MARKDOWN_URL` **en** `DEFAULT_CONVERT_FORMAT_URL`: `/scratch-pad/...` → `/note/...`. **Let op:** views die een custom `importTextUrl`/`importMarkdownUrl` injecteren via config (bijv. `views/context/_form.php`) gebruiken de Yii `Url::to()` helper — deze genereren automatisch de juiste URL na controller-hernoaming. De defaults in editor-init.js zijn alleen van toepassing als geen config override wordt meegegeven. |
| `yii/assets/smart-paste/smart-paste.js` | `IMPORT_URL` constant: `/scratch-pad/import-text` → `/note/import-text` |
| `yii/web/quill/1.3.7/editor-init.min.js` | Regenereren na source-wijziging (`npm run build-init`) |

### JavaScript — client-side keys

| Item | Van | Naar | Opmerking |
|------|-----|------|-----------|
| `localStorage` key | `scratchPadContent` | `noteContent` | Alleen in `create.php` — tijdelijke import-data, breaking change acceptabel |
| `sessionStorage` key | `claudePromptContent` | `claudePromptContent` | **Geen wijziging** — geen scratch-pad referentie |
| Accordion ID (create) | `scratchPadAccordion` | `noteAccordion` | |
| Accordion ID (view) | `scratchPadViewAccordion` | `noteViewAccordion` | |
| Modal ID | `scratchPadImportModal` | `noteImportModal` | |
| HTML form/input ID's (`_form.php`) | `scratch-pad-name`, `scratch-pad-project`, `scratch-pad-content`, `scratch-pad-response`, `scratch-pad-editor`, `scratch-pad-response-editor`, `scratch-pad-form`, `scratch-pad-import-*` | `note-name`, `note-project`, `note-content`, `note-editor`, `note-form`, `note-import-*` | Response-gerelateerde IDs verdwijnen |
| HTML editor ID's (`create.php`) | `#editor`, `#response-editor` | `#editor` | Geen hernoaming nodig (geen prefix). Response-editor verdwijnt. |
| ActiveForm-generated ID | `scratchpad-project_id` (in Save As) | `note-project_id` | |

> **Let op:** De `localStorage` key-wijziging is een client-side breaking change. Bestaande data in de browser onder de oude key gaat verloren. Dit is acceptabel omdat het enkel tijdelijke import-data betreft.

### Tests

| Bestand | Actie |
|---------|-------|
| `tests/unit/models/ScratchPadTest.php` | Hernoem → `NoteTest.php`, pas class/model-referenties aan |
| `tests/unit/models/query/ScratchPadQueryTest.php` | Hernoem → `NoteQueryTest.php`, voeg tests toe voor `forParent()`, `topLevel()`, `withChildren()` |
| `tests/unit/controllers/ScratchPadControllerTest.php` | Hernoem → `NoteControllerTest.php` |
| `tests/unit/controllers/api/ScratchPadControllerTest.php` | Hernoem → `api/NoteControllerTest.php` |
| `tests/unit/services/QuickSearchServiceTest.php` | Referenties updaten: method-namen, type strings, result keys |
| `tests/unit/services/AdvancedSearchServiceTest.php` | Referenties updaten: constants, method-namen |
| `tests/unit/handlers/ClaudeQuickHandlerTest.php` | Referenties updaten: use-case key `'scratch-pad-name'` → `'note-name'` |
| Nieuwe tests in `NoteTest.php` | `testNoteTypeValidation`, `testParentChildRelation`, `testTopLevelScope`, `testOrphanBehavior` |
| Nieuwe tests in `NoteControllerTest.php` | `testFetchContentMergesChildContent` — verificatie dat fetchContent parent + children Delta's samenvoegt |
| Nieuwe tests in `NoteControllerTest.php` | `testImportSetsTypeImport` — verificatie dat import-acties `type = NoteType::IMPORT` zetten |
| Nieuwe test in `NoteSearchTest.php` | `testFilterByType` — verificatie dat type-filter in NoteSearch werkt |
| Nieuwe test in `NoteSearchTest.php` | `testShowChildrenToggle` — verificatie dat `showChildren=true` ook child notes toont |
| Nieuwe test in `NoteServiceTest.php` | `testFetchMergedContent` — verificatie van het Delta merge-algoritme |
| Nieuwe test in `NoteServiceTest.php` | `testSaveNoteWithParentId` — verificatie ownership-check op parent_id |

### Documentatie / Config (geen functionele impact)

| Bestand | Actie |
|---------|-------|
| `CLAUDE.md` | Entity-referentie updaten |
| `.claude/codebase_analysis.md` | ScratchPad → Note in alle secties |
| `.claude/config/project.md` | Tabel + rule referenties |
| `.claude/prompts/*.md` | Entity-graaf updaten |
| `.claude/design/suggest-scratch-pad-name/` | Hernoemen → `suggest-note-name/` |
| `.claude/workdirs/scratch-pad-name/` | Hernoemen → `note-name/` |
| `.claude/rules/skill-routing.md` | Eventuele referenties naar scratch-pad updaten |

---

## 8. Niet in scope

- **Nesting dieper dan 1 niveau** — parent_id ondersteunt het technisch, maar UI toont alleen 1 niveau (parent → children)
- **Drag & drop ordening** van children — buiten scope
- **Type-specifiek gedrag** — alle types delen dezelfde editor. Eventueel later type-specifieke views
- **Retroactieve herclassificatie** — bestaande imports worden niet automatisch type=import
- **URL redirects** — geen 301 van `/scratch-pad/*` naar `/note/*`

---

## 9. Implementatievolgorde

1. **NoteType enum** aanmaken (`common/enums/NoteType.php`)
2. **Migratie 1** — tabel hernoemen + FK's/indexes + kolommen toevoegen
3. **Migratie 2a** — response-data migreren naar child notes (PHP-loop)
4. **Migratie 2b** — response-kolom verwijderen
5. **Migratie 3** — RBAC hernoemen (via `authManager` API + `EntityPermissionService::invalidatePermissionCache()`)
6. **Model-laag** — Note, NoteQuery, NoteSearch, NoteOwnerRule
7. **NoteService** — `saveNote()`, `deleteNote()`, `fetchMergedContent()` — voor controllers zodat controllers hier naar kunnen delegeren
8. **Controllers** — NoteController (web + API), delegeert save/fetch-logica naar NoteService, inclusief `parent_id` ownership-validatie en `type`-validatie
9. **Views** — verplaatsen + aanpassen (incl. views buiten note-directory, incl. claude/index.php save-flow §3.14)
10. **Services** — search (incl. child→parent URL-logica), sync (incl. parent_id FK + sorteer-logica), projectload (alle EntityConfig methodes), handlers
11. **Config** — rbac.php (entity key + permissions + rule class), main.php, ProjectUrlManager
12. **JavaScript** — editor-init.js (3 URL-constanten), smart-paste.js + rebuild min
13. **Tests** — hernoemen + aanpassen + nieuwe tests (type validatie, parent/child, fetchContent merge, import type, search filter, parent_id ownership, NoteService)
14. **Documentatie** — CLAUDE.md, codebase_analysis, prompts, design dirs, workdirs, skill-routing

---

## 10. Beslissingen (voorheen open vragen)

### 10.1 Fetch-content samenvoegformaat — BESLOTEN
Parent Delta gevolgd door children Deltas in `created_at` volgorde, samengevoegd als een enkel Quill Delta JSON document met dubbele newline insert als separator. Response-structuur blijft `{ success, content, projectId }`. Zie edge case 3.7 voor details en merge-algoritme.

### 10.2 Search result rendering frontend — OPGELOST
Geidentificeerde bestanden:
- `yii/web/js/quick-search.js` — group label `scratchPads: "Scratch Pads"`
- `yii/web/js/advanced-search.js` — group label `scratchPads: "Scratch Pads"`
- `views/layouts/_advanced-search-modal.php` — dynamisch via `AdvancedSearchService::typeLabels()`, geen handmatige wijziging nodig

Toegevoegd aan impactanalyse sectie 7 ("JavaScript — search rendering").

### 10.3 Save-flow create vs update — BESLOTEN
De bestaande flow-scheiding blijft behouden: create-view gebruikt AJAX `actionSave()`, update-view gebruikt ActiveForm met `_form.php`. Beide flows worden enkel ontdaan van het `response`-veld. Geen unificatie — dat is buiten scope.

### 10.4 Migratie 2a implementatie — BESLOTEN
PHP-loop migratie in plaats van pure SQL. De `isEmptyDelta()` helper decodeert JSON en vergelijkt structureel, waardoor whitespace-variaties in opgeslagen Quill Delta JSON geen probleem vormen. Zie §6 Migratie 2a voor de implementatie.

### 10.5 EntityConfig list label — BESLOTEN
`'SP'` wordt `'Nt'` (consistent met 2-3 letter afkortingen: Ctx, Fld, Tpl, Inst).

### 10.6 Sync self-referencing FK — BESLOTEN
EntityDefinitions krijgt `'parent_id' => 'note'` als FK-definitie. Bij import wordt gesorteerd op `parent_id IS NULL DESC` zodat parent notes eerst worden verwerkt. De bestaande FK-resolutie in de sync engine handelt de ID-mapping automatisch af.

### 10.7 NoteService architectuur — BESLOTEN
`NoteService` is een standalone class (niet extend Component) zonder constructor dependencies in v1, vergelijkbaar met `FieldService`. Accepteert associative array voor `saveNote()` — consistent met huidige controller-patroon. DTO-refactoring kan later indien nodig.

### 10.8 `response-summary` use case — BESLOTEN
De workdir `.claude/workdirs/response-summary/` en use case key `'response-summary'` zijn onafhankelijk van de scratch-pad naamgeving. Geen hernoaming nodig.

### 10.9 `created_at`/`updated_at` kolomtype — BESLOTEN
INT (unix timestamps) — geverifieerd via originele migratie `m251225_000001_create_scratch_pad_table.php`: `$this->integer()->notNull()`. TimestampTrait gebruikt `time()`.

### 10.10 RBAC cascading via `auth_item_child` — BESLOTEN
`$auth->update()` werkt op de `auth_item` tabel. De `auth_item_child` koppeltabel heeft FK's naar `auth_item.name` met CASCADE ON UPDATE, waardoor role-permission assignments automatisch meegaan bij hernoaming. Dit is standaard Yii2 RBAC-gedrag.

### 10.11 Claude/index.php save-flow — BESLOTEN
Two-step save (parent note → response-child note) conform §3.14. De JavaScript in claude/index.php wordt aangepast om twee sequentiele `POST /note/save` requests te doen.

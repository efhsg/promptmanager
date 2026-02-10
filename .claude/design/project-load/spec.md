# Specificatie: Project Load uit Database Dump

## Metadata

| Eigenschap | Waarde |
|------------|--------|
| Versie | 1.5 |
| Status | Concept |
| Datum | 2026-02-10 |

---

## 1. Overzicht

Vervang de bestaande bidirectionele sync-functionaliteit (SSH-tunnel + MySQL, `yii/services/sync/`) door een eenvoudiger, eenrichtingsmodel: laad individuele projecten uit een MySQL-dumpbestand. De gebruiker maakt zelf een `mysqldump`, specificeert welke project-IDs geladen moeten worden, en het systeem importeert de data met optioneel dry-run rapport. Het bestaande project wordt eerst verwijderd (incl. alle gerelateerde entiteiten) voordat de dump-data wordt ingeladen.

**Waarom:** De huidige sync is complex (7 klassen, SSH-tunnel, bidirectioneel, natural key matching) en heeft bekende bugs (placeholder-IDs niet geremapped, `scratch_pad.response` niet gesynced, state leakage). Een eenvoudiger dump-gebaseerd model lost de kernbehoefte op (projecten uitwisselen tussen omgevingen, herstellen vanuit backup) zonder die complexiteit.

---

## 2. Huidig gedrag

### CLI commando's (worden verwijderd)

```bash
yii sync/status     # Dry-run overzicht pull + push
yii sync/pull       # Remote → local
yii sync/push       # Local → remote
yii sync/run        # Bidirectioneel (pull dan push)
```

### Bestaande sync-architectuur

```
SyncController (CLI)
  └─ SyncService (orchestratie)
       ├─ RemoteConnection (SSH-tunnel + MySQL)
       ├─ EntitySyncer (natural key matching, FK remapping, insert/update)
       │    ├─ RecordFetcher (user-scoped queries)
       │    └─ ConflictResolver (last-write-wins)
       ├─ EntityDefinitions (schema metadata)
       └─ SyncReport (ID mappings, statistieken)
```

**Bekende bugs in huidige sync:**

| # | Bug | Impact |
|---|-----|--------|
| 1 | Placeholder-IDs in `template_body` niet geremapped | Kapotte placeholders na sync |
| 2 | `scratch_pad.response` ontbreekt in `EntityDefinitions` | Dataverlies |
| 3 | `EntitySyncer` hergebruikt voor pull + push | State leakage |
| 4 | Soft-deleted projecten worden mee-gesynced | Verwijderde data verschijnt opnieuw |

### Project-verwijdering

`ProjectController::actionDelete()` roept `$model->delete()` aan. Database FK CASCADE verwijdert:
- context, field (+field_option), prompt_template (+template_field), scratch_pad, project_linked_project

**Kritiek probleem:** `prompt_instance.template_id` is aangemaakt met `ON DELETE RESTRICT` in de initiele migratie (`m230101_000001`). Een latere migratie (`m250610_000002`) probeert CASCADE toe te voegen maar is een no-op op bestaande databases (zie §9 aanname 6). Dit betekent dat `$project->delete()` **faalt** als er prompt_instances bestaan. De huidige controller vangt dit op in een try/catch en toont een generieke foutmelding. De nieuwe load-functionaliteit moet prompt_instances **altijd** expliciet verwijderen vóór projectverwijdering, ongeacht de actuele FK-constraint — dit is defensief en veilig voor beide situaties.

---

## 3. Nieuw gedrag

### 3.1 CLI Commando's

```bash
# Toon projecten beschikbaar in dump
yii project-load/list <dump-bestand>

# Laad specifieke projecten uit dump
yii project-load/load <dump-bestand> --project-ids=5,8,12

# Dry-run: toon impact zonder wijzigingen
yii project-load/load <dump-bestand> --project-ids=5,8,12 --dry-run

# Inclusief globale velden die door templates gerefereerd worden
yii project-load/load <dump-bestand> --project-ids=5,8,12 --include-global-fields

# Expliciete lokale project-ID matching (dump-ID=5 → lokaal ID=12)
yii project-load/load <dump-bestand> --project-ids=5 --local-project-ids=12

# Alternatieve user-id (standaard: 1)
yii project-load/load <dump-bestand> --project-ids=5,8,12 --user-id=2

# Ruim orphaned tijdelijke schemas op
yii project-load/cleanup
```

### 3.2 Flow: `project-load/list`

```
1. Valideer dump-bestand (bestaat, leesbaar, .sql extensie)
2. Maak tijdelijk MySQL-schema (yii_load_temp_{pid}, bijv. `yii_load_temp_12345`)
   - Unieke naam per process voorkomt race conditions bij parallel gebruik
   - Character set: `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` (zelfde als productie-schema)
   - Check en verwijder eventuele orphaned yii_load_temp_* schemas ouder dan 1 uur
3. Importeer dump (strip USE-statements voor veiligheid)
4. Query projecten uit tijdelijk schema
5. Toon tabel met projecten en aantallen gerelateerde entiteiten
6. Verwijder tijdelijk schema
```

**Voorbeeld output:**

```
Projecten in dump:

  ID │ Naam            │ Ctx │ Fld │ Tpl │ Inst │ SP │ Links
 ────┼─────────────────┼─────┼─────┼─────┼──────┼────┼──────
   5 │ MyApp           │   4 │  10 │   6 │   20 │  3 │    2
   8 │ SharedLib       │   2 │   5 │   3 │    8 │  1 │    0
  12 │ NewProject      │   1 │   3 │   2 │    5 │  0 │    1

Totaal: 3 projecten
```

Kolommen: Ctx = Contexts, Fld = Fields (project-gebonden), Tpl = Prompt Templates, Inst = Prompt Instances, SP = Scratch Pads, Links = Linked Projects.

### 3.3 Flow: `project-load/load`

```
1. Valideer invoer
   a. Dump-bestand bestaat en is leesbaar (.sql of .dump extensie, via realpath())
   b. --project-ids bevat geldige integers
   c. --user-id verwijst naar bestaande user
   d. Als --local-project-ids opgegeven: zelfde aantal als --project-ids, geldige integers,
      verwijzen naar bestaande projecten van user

2. Maak tijdelijk MySQL-schema (yii_load_temp_{pid})
   - Unieke naam per process voorkomt race conditions bij parallel gebruik
   - `CREATE DATABASE yii_load_temp_{pid} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
   - Check en verwijder eventuele orphaned yii_load_temp_* schemas ouder dan 1 uur

3. Importeer dump in tijdelijk schema
   a. Strip `USE`, `CREATE DATABASE` en `DROP DATABASE` regels uit dump
      (voorkom schrijven naar of aanmaken van onbedoelde schema's)
   b. mysql yii_load_temp < (gefilterde dump)

4. Valideer schema-compatibiliteit
   a. Vergelijk INFORMATION_SCHEMA.COLUMNS van temp-schema met productie-schema per entiteit
   b. Waarschuwing per kolom die in productie-schema staat maar ontbreekt in dump
      (zal NULL worden bij insert)
   c. Fout bij ontbrekende primaire sleutel- of FK-kolommen

5. Voor elk gevraagd project-ID:
   a. Haal project op uit tijdelijk schema
      - Als niet gevonden: waarschuwing, ga door met volgend ID
      - Als deleted_at IS NOT NULL: waarschuwing ("project is soft-deleted in dump"), overslaan
   b. Haal alle gerelateerde entiteiten op (zie §3.5)
   c. Bepaal lokale match (zie §3.3.1 Matching-strategie)
   d. Als --include-global-fields:
      - Identificeer globale velden gerefereerd in template_body placeholders
      - Haal die velden + hun field_options op uit tijdelijk schema
   e. Als --dry-run: genereer rapport (§3.4), ga door met volgend ID
   f. Begin database-transactie
   g. Als lokaal project gevonden:
      - Bewaar het lokale project-ID voor hergebruik
      - Verwijder prompt_instances (ON DELETE RESTRICT vereist expliciete delete)
      - Verwijder lokaal project ($model->delete() — CASCADE verwijdert rest)
   h. Insert project met behoud van lokaal ID (bij vervanging) of nieuw auto-increment ID
      (bij nieuw project)
      - Bij vervanging: expliciet het bewaarde lokale ID meegeven als kolomwaarde. Dit voorkomt
        gebroken referenties vanuit andere projecten (project_linked_project), sessie-data
        (ProjectContext), en browser-bookmarks.
      - **Gebruik raw insert** (`Yii::$app->db->createCommand()->insert()`) voor het project-record.
        Dit is noodzakelijk omdat:
        1. `Project` gebruikt `TimestampTrait` — ActiveRecord `save()` overschrijft
           `created_at`/`updated_at`
        2. `Project::afterSave()` triggert `claudeWorkspaceService->syncConfig()` op een
           half-geladen project (children zijn nog niet geïnserteerd)
        3. Consistentie met de raw-insert aanpak voor children (stap 5i)
      - Bij nieuw project: laat `id` weg uit de insert, MySQL auto-increment bepaalt het ID.
        Haal het nieuwe ID op via `Yii::$app->db->getLastInsertID()`.
   i. Insert children in afhankelijkheidsvolgorde (§3.6), remap FK-referenties
      - **Let op: TimestampTrait.** Alle entiteiten gebruiken `TimestampTrait`, dat
        `created_at`/`updated_at` overschrijft in `beforeSave()`. Om dump-timestamps te
        behouden: gebruik raw inserts via `Yii::$app->db->createCommand()->insert()` i.p.v.
        ActiveRecord. Dit geldt voor alle entiteiten inclusief het project zelf (stap 5h).
   j. Remap placeholder-IDs in template_body (§3.7)
   k. Commit transactie (of rollback bij fout)

6. Verwijder tijdelijk schema

7. Toon eindrapport (per project: succes/fout + statistieken)
   - Expliciet vermelden welke projecten succesvol geladen zijn
   - Expliciet vermelden welke projecten gefaald zijn met reden
   - Bij gedeeltelijk succes: waarschuwing dat sommige projecten wél geladen zijn
```

### 3.3.1 Matching-strategie

Matching bepaalt welk lokaal project vervangen wordt door de dump-data. Projectnamen zijn **niet** uniek per gebruiker (geen database constraint, geen model validatie), dus naamsmatching alleen is onvoldoende.

**Prioriteitsvolgorde:**

1. **`--local-project-ids` (expliciet):** Als opgegeven, wordt elk dump-project-ID 1:1 gekoppeld aan het corresponderende lokale project-ID. Aantallen moeten gelijk zijn. Dit is de veiligste optie.
2. **Naam + user_id (automatisch, fallback):** Als `--local-project-ids` niet opgegeven is, zoek lokaal project op `name + user_id`.
   - **Geen match:** nieuw project aanmaken.
   - **Precies één match:** dat project wordt vervangen.
   - **Meerdere matches:** fout voor dit project. Toon de gevonden projecten (ID + naam) en instrueer de gebruiker om `--local-project-ids` te gebruiken.

**Voorbeeld foutmelding:**

```
FOUT: Meerdere lokale projecten gevonden met naam "MyApp" voor user 1:
  ID 12: "MyApp" (aangemaakt 2025-06-01)
  ID 34: "MyApp" (aangemaakt 2025-11-15)
Gebruik --local-project-ids=12 om expliciet te matchen.
```

### 3.4 Dry-run rapport

Per project een gedetailleerd overzicht van de impact:

```
═══════════════════════════════════════════════════════
Project: "MyApp" (dump ID: 5)
───────────────────────────────────────────────────────
Lokale match: "MyApp" (ID: 12) → WORDT VERWIJDERD

  Wordt verwijderd (lokaal):
    1 project
    3 contexts: Default, Backend, Frontend
    8 fields (+12 field options)
    5 prompt templates (+4 template fields, +15 prompt instances)
    2 scratch pads

  Wordt geladen (uit dump):
    1 project
    4 contexts: Default, Backend, Frontend, API
   10 fields (+15 field options)
    6 prompt templates (+5 template fields, +20 prompt instances)
    3 scratch pads
    2 project links

  Waarschuwingen:
    ⚠ Gelinkt project "Utils" (dump ID: 20) bestaat niet lokaal → link overgeslagen
    ⚠ Template "Code Review" refereert globaal veld "Author" (GEN:{{15}})
      dat lokaal niet bestaat. Gebruik --include-global-fields om mee te laden.
    ⚠ Template "Deploy" refereert extern veld "env" uit project "Infra"
      dat lokaal niet gevonden is → placeholder behouden met dump-ID
    ℹ root_directory, claude_options, claude_context worden niet geladen
      (machine-specifiek) — configureer na het laden.
    ℹ Lokaal project is soft-deleted (deleted_at = 2026-01-15). Na load wordt
      het opnieuw actief.

═══════════════════════════════════════════════════════
Project: "NewProject" (dump ID: 8)
───────────────────────────────────────────────────────
Lokale match: GEEN → nieuw project

  Wordt geladen (uit dump):
    1 project
    2 contexts: Default, API
    5 fields (+8 field options)
    3 prompt templates (+3 template fields, +10 prompt instances)
    1 scratch pad

═══════════════════════════════════════════════════════
Samenvatting: 2 projecten, 1 vervanging, 1 nieuw
```

### 3.5 Entiteiten en kolom-detectie

#### 3.5.1 Dynamische kolom-detectie

Kolomlijsten worden **niet** hardcoded maar runtime bepaald via `INFORMATION_SCHEMA.COLUMNS` van het productie-schema. Dit elimineert de categorie "vergeten kolom bijwerken na migratie" — de belangrijkste onderhoudslast van de oude `EntityDefinitions`.

**Mechanisme:**

```
Voor elke entiteit in de load-configuratie:
1. Query INFORMATION_SCHEMA.COLUMNS van het productie-schema voor de tabelnaam
2. Filter:
   a. Verwijder auto-increment PK kolommen (EXTRA = 'auto_increment')
   b. Verwijder kolommen uit de exclude-lijst (§3.9)
3. Resultaat = SELECT-kolomlijst voor temp-schema query + INSERT-kolomlijst voor productie
4. Bij SELECT uit temp-schema: als een kolom niet bestaat in het temp-schema
   (oudere dump), gebruik NULL als waarde
```

**Wat dynamisch is (kolomlijsten):**
- Welke kolommen gelezen worden uit het temp-schema
- Welke kolommen geschreven worden naar productie
- Nieuwe kolommen worden automatisch meegenomen

**Wat handmatig/configuratief blijft:**
- Entiteiten-lijst en hun onderlinge relaties (§3.5.2)
- Insert-volgorde (§3.6)
- FK-remapping regels (welk veld naar welke parent-entiteit verwijst)
- Kolom-excludes (§3.9) en kolom-overrides (§3.8)
- Placeholder-remapping logica (§3.7)

#### 3.5.2 Entiteiten-configuratie

Per entiteit wordt geconfigureerd: tabelnaam, bron-query, FK-relaties en speciale behandeling. De kolomlijsten worden dynamisch bepaald (§3.5.1).

| Entiteit | Tabel | Bron-query (temp schema) | FK-remapping | Opmerkingen |
|----------|-------|--------------------------|--------------|-------------|
| project | `project` | `WHERE id = :projectId` | — | Root entiteit. `user_id` → `--user-id` override. Kolommen uit §3.9 → NULL. `description` = Quill Delta JSON. |
| context | `context` | `WHERE project_id = :projectId` | `project_id` → project | Quill Delta content. |
| field | `field` | `WHERE project_id = :projectId` | `project_id` → project, `user_id` → override | Project-gebonden velden. |
| field_option | `field_option` | `WHERE field_id IN (:projectFieldIds)` | `field_id` → field | Opties van project-velden. |
| prompt_template | `prompt_template` | `WHERE project_id = :projectId` | `project_id` → project | Template body = Quill Delta met placeholder-IDs. Na insert: placeholder-remapping (§3.7). |
| template_field | `template_field` | `WHERE template_id IN (:projectTemplateIds)` | `template_id` → prompt_template, `field_id` → field | Pure pivot-tabel: composite PK, geen auto-increment, geen timestamps. |
| prompt_instance | `prompt_instance` | `WHERE template_id IN (:projectTemplateIds)` | `template_id` → prompt_template | `final_prompt` is gerenderde output, geen remapping nodig (§3.7.1). |
| scratch_pad | `scratch_pad` | `WHERE project_id = :projectId` | `project_id` → project, `user_id` → override | Inclusief `response` kolom (ontbrak in oude sync). Filtert impliciet globale scratch pads uit (project_id IS NULL). |
| project_linked_project | `project_linked_project` | `WHERE project_id = :projectId` | `project_id` → project | Eenrichtings: alleen links vanuit dit project. Alleen als gelinkt project lokaal bestaat (§3.5.4). **Let op:** timestamps zijn integer (UNIX) i.p.v. string-format. |

**Optioneel met `--include-global-fields`:**

| Entiteit | Tabel | Bron-query | FK-remapping | Opmerkingen |
|----------|-------|------------|--------------|-------------|
| field (globaal) | `field` | `WHERE project_id IS NULL AND id IN (:referencedGlobalFieldIds)` | `user_id` → override | Alleen velden gerefereerd door dit project's templates |
| field_option (globaal) | `field_option` | `WHERE field_id IN (:globalFieldIds)` | `field_id` → field | Opties van geladen globale velden |

#### 3.5.3 Globale velden: match-en-hergebruik strategie

Globale velden worden gematcht op `name + user_id`:

- **Bestaat lokaal:** het **lokale veld behouden** en het lokale ID gebruiken voor remapping. Het globale veld wordt **niet** overschreven, omdat andere lokale projecten/templates ernaar kunnen verwijzen. Bestaande lokale globale velden behouden hun eigen `user_id`.
  - **Type verschilt:** waarschuwing in rapport met details (bijv. "Globaal veld 'Language' is lokaal type 'select' maar type 'text' in dump"). Het lokale veld wordt behouden — de gebruiker moet dit handmatig oplossen als het template ander gedrag verwacht.
  - **Aantal opties verschilt:** waarschuwing in rapport. Geen actie — het lokale veld heeft mogelijk andere opties die door andere projecten worden gebruikt.
  - In de dry-run worden type-verschillen als **prominente waarschuwing** getoond (niet als informatiemelding), omdat ze functioneel gedrag beïnvloeden.
- **Bestaat niet lokaal:** nieuw aanmaken met data uit de dump. Nieuw aangemaakte globale velden krijgen de `--user-id`.

**Rationale:** Globale velden zijn per definitie gedeeld tussen projecten. Overschrijven met dump-data kan onbedoeld content wijzigen die door andere lokale projecten wordt gebruikt.

#### 3.5.4 Project links: eenrichtingsmodel

`project_linked_project` is een eenrichtingsrelatie: een record `(project_id=A, linked_project_id=B)` betekent dat project A linkt naar project B. Er wordt **geen** omgekeerd record `(B, A)` aangemaakt. Bij het laden worden alleen links **vanuit** het geladen project meegenomen.

**Laadvolgorde is relevant:** Als de dump projecten A en B bevat, en A linkt naar B, laad dan B vóór A (of beide in dezelfde run). Worden ze in aparte runs geladen, dan wordt de link vanuit A naar B pas correct als B al lokaal bestaat op het moment dat A geladen wordt.

**Inkomende links vanuit andere lokale projecten:** Doordat het lokale project-ID behouden wordt bij vervanging (§3.3 stap 5h), blijven `project_linked_project`-records vanuit andere lokale projecten die naar dit project verwijzen intact. Dit is een belangrijk voordeel van ID-hergebruik.

### 3.6 Insert-volgorde

Entiteiten worden in afhankelijkheidsvolgorde geïnserteerd (ouders voor kinderen):

```
1. project
2. field (project-gebonden + optioneel globaal)
3. field_option
4. context
5. prompt_template
6. template_field
7. scratch_pad
8. prompt_instance
9. project_linked_project (alleen als gelinkt project lokaal bestaat)
```

`field_option` moet na `field` (FK `field_id → field`). `template_field` moet na zowel `prompt_template` als `field` omdat het naar beide verwijst (`template_id → prompt_template`, `field_id → field`).

### 3.7 Placeholder-ID remapping

`template_body` bevat placeholders als `PRJ:{{42}}`, `GEN:{{15}}`, `EXT:{{8}}` waar de nummers field-IDs zijn uit de brondata. Bij het laden veranderen deze IDs.

**Remapping-strategie per placeholder-type:**

| Type | Bron | Remapping |
|------|------|-----------|
| `PRJ:{{id}}` | Project-veld, mee geladen | Gebruik ID mapping van geladen velden |
| `GEN:{{id}}` | Globaal veld | Als geladen (--include-global-fields): gebruik mapping. Anders: zoek lokaal veld met zelfde naam, gebruik lokaal ID. Als niet gevonden: bewaar origineel ID + waarschuwing |
| `EXT:{{id}}` | Veld uit ander project | Zoek veld in temp schema → bepaal veldnaam + bronproject → zoek lokaal project via `label + user_id` (uniek per user, database constraint). Fallback: `name + user_id` (alleen bij precies één match). → zoek lokaal veld (`name + project_id` — uniek per project via model validatie) → gebruik lokaal ID. Als project niet gevonden, label ontbreekt in dump of lokaal, of meerdere name-matches: bewaar origineel ID + waarschuwing |

**Remapping-implementatie (conform bestaand patroon):**

De codebase past placeholder-regex altijd toe op individuele Quill Delta ops, niet op de ruwe JSON-string. De remapping moet dit patroon volgen:

1. Parse `template_body` als JSON → `$delta['ops']` array
2. Itereer over elke op: als `$op['insert']` een string is, pas regex toe
3. Regex: `/(GEN|PRJ|EXT):\{\{(\d+)\}\}/`
4. Vervang gematchte IDs via de ID-mapping
5. Re-encode naar JSON

**Referentie-implementatie:** `PromptTemplateService::convertPlaceholdersToIds()` (`yii/services/PromptTemplateService.php:116-143`) volgt exact dit patroon.

**Waarom niet op de ruwe JSON-string?** Regex op de ruwe string riskeert false positives in JSON-attributen en wijkt af van het gevestigde codebase-patroon.

Dit lost ook de bestaande sync-bug op (placeholder-IDs werden niet geremapped).

### 3.7.1 `prompt_instance.final_prompt` — geen remapping

`prompt_instance.final_prompt` bevat de **gerenderde output**: veld-waarden zijn al ingevuld door `PromptGenerationService` op het moment van generatie. Dit is Quill Delta JSON met concrete content, geen placeholder-referenties. Er is daarom **geen** ID-remapping nodig voor deze kolom.

**`template_field` records:**

Template_field records koppelen template_id aan field_id. Na remapping van beide IDs:
- Als field_id voorkomt in de ID-mapping van geladen project-velden: gebruik geremapte ID
- Als field_id voorkomt in de ID-mapping van geladen globale velden: gebruik geremapte of lokale ID
- Als field_id in **geen van beide** mappings voorkomt: behandel als extern veld (EXT) — zoek lokaal ID via dezelfde strategie als EXT-placeholder remapping in §3.7 (label + user_id → veldnaam + project_id)
- Als veld niet gevonden: template_field record overslaan + waarschuwing

### 3.8 User-ID afhandeling

- Alle geladen data krijgt de lokale `--user-id` (standaard: 1)
- De user_id uit de dump wordt **niet** overgenomen
- Dit geldt voor: `project.user_id`, `field.user_id`, `scratch_pad.user_id`
- **Uitzondering:** bestaande lokale globale velden behouden hun eigen `user_id` (§3.5.3)

### 3.9 Kolommen die NIET geladen worden

| Kolom | Reden |
|-------|-------|
| `project.claude_options` | Machine-specifiek (Claude CLI configuratie) |
| `project.claude_context` | Machine-specifiek (Quill Delta met project-instructies) |
| `project.root_directory` | Machine-specifiek (bestandspad) |

Deze kolommen worden op `NULL` gezet bij het laden. Als het project al lokaal bestond, gaan deze waarden verloren — dat is de gewenste situatie (ze moeten per machine geconfigureerd worden).

**Let op:** `project.root_directory` is machine-specifiek en moet na het laden opnieuw geconfigureerd worden. Bij de dry-run wordt dit als melding getoond.

**Configuratie:** De exclude-lijst wordt op één plek gedefinieerd (constante of configuratie-array in de service). Bij wijzigingen hoeft alleen deze lijst bijgewerkt te worden — de dynamische kolom-detectie (§3.5.1) past de rest automatisch aan.

---

## 4. Bestaande infrastructuur

| Component | Pad | Relevantie |
|-----------|-----|------------|
| `EntityDefinitions` | `yii/services/sync/EntityDefinitions.php` | Kolom-definities per entiteit. **Niet hergebruikt** — vervangen door dynamische kolom-detectie (§3.5.1). Laat zien welk probleem (verouderde lijsten: ontbreekt `scratch_pad.response`, `project.claude_options`, `project.claude_context`) de dynamische aanpak voorkomt. |
| `RecordFetcher` | `yii/services/sync/RecordFetcher.php` | Query-patronen per entiteit met user-scoping. Conceptueel herbruikbaar voor het ophalen uit temp schema, maar scoping is anders (per project-ID, niet per user). |
| `EntitySyncer` | `yii/services/sync/EntitySyncer.php` | FK remapping via `mapForeignKeys()` en `SyncReport.idMappings`. Concept herbruikbaar, maar implementatie te verweven met bidirectionele sync-logica. |
| `SyncReport` | `yii/services/sync/SyncReport.php` | ID mapping opslag + rapportage. Concept herbruikbaar voor nieuwe `LoadReport`. |
| `SyncController` | `yii/commands/SyncController.php` | CLI output formatting (kleuren, statistieken). Pattern voor nieuwe controller. |
| `Project::afterDelete()` | `yii/models/Project.php:584` | Ruimt Claude workspace op via `claudeWorkspaceService->deleteWorkspace()`. Wordt automatisch aangeroepen bij `$model->delete()`. **Bij project-vervanging:** de workspace van het oude project wordt verwijderd. Dit is gewenst — het nieuwe project krijgt toch `claude_options`/`claude_context` op NULL (§3.9), dus de workspace moet per machine opnieuw geconfigureerd worden. |
| `Project::afterSave()` | `yii/models/Project.php:560` | Synct Claude workspace config bij insert of relevante field changes. Wordt NIET aangeroepen bij raw insert (wat wij doen). Na insert moet `claudeWorkspaceService->syncConfig()` apart aangeroepen worden als dit gewenst is. Omdat `claude_options` en `claude_context` op NULL staan na load, is workspace-sync optioneel (er is niets te syncen). |
| `TimestampTrait` | `yii/models/traits/TimestampTrait.php` | Timestamp-afhandeling. Niet relevant — we kopiëren timestamps uit de dump via raw inserts. |

---

## 5. Toegangscontrole

- **Uitsluitend CLI** — geen web-UI, geen RBAC nodig
- Console commands vallen buiten Yii2 AccessControl behaviors
- `--user-id` parameter bepaalt eigenaar van geladen data
- Bestandstoegang: dump-bestand moet leesbaar zijn voor de PHP-procesuser
- MySQL-user moet `CREATE DATABASE` en `DROP DATABASE` rechten hebben voor het tijdelijk schema

### 5.1 Dump-import: implementatie-keuze

De dump wordt geïmporteerd via de `mysql` CLI binary (`proc_open()`). Dit is efficiënter dan PHP-gebaseerde import (PDO statement-voor-statement) voor grote bestanden, en de `mysql` binary is standaard beschikbaar in de Docker-container.

**Alternatief (PDO):** Voor kleine dumps (<10MB) zou Yii's `Yii::$app->db->createCommand($sql)->execute()` volstaan, zonder shell-afhankelijkheden. Dit is een implementatie-keuze voor de architect.

### 5.2 Bestandspad-sanitization

Het dump-bestandspad wordt als argument aan een shell-commando doorgegeven (`mysql ... < bestand`). Om shell injection te voorkomen:

- Valideer het pad via `realpath()` — het bestand moet daadwerkelijk bestaan
- Valideer extensie (`.sql` of `.dump`)
- Gebruik `escapeshellarg()` voor het pad in het shell-commando
- Voer mysql uit via `proc_open()` of Yii2's `Process`, **niet** via `shell_exec()` of backticks
- Geen user-input in de SQL die naar het temp-schema gestuurd wordt (schema-naam is per-process gegenereerd, niet door gebruiker bepaald)

---

## 6. Edge cases en foutafhandeling

| Scenario | Gedrag |
|----------|--------|
| Dump-bestand niet gevonden of niet leesbaar | Fout, stop. Exit code 1. |
| Dump-import naar temp schema mislukt | Fout, cleanup temp schema, stop. Exit code 1. |
| `--project-ids` bevat niet-numerieke waarden | Validatiefout, stop. |
| Project-ID niet gevonden in dump | Waarschuwing per ID, ga door met overige IDs. |
| Project in dump heeft `deleted_at` gevuld | Waarschuwing ("project is soft-deleted in dump"), overslaan. |
| Lokaal project met zelfde naam bestaat niet | Nieuw project aanmaken (geen delete). |
| Lokaal project met zelfde naam bestaat wel (één match) | Delete prompt_instances, dan delete project (CASCADE), dan insert. |
| Lokaal project is soft-deleted (deleted_at IS NOT NULL) | Waarschuwing in dry-run rapport: "Lokaal project is soft-deleted. Na load wordt het opnieuw actief." Load gaat door — de dump is de bron van waarheid. |
| Meerdere lokale projecten met zelfde naam (zonder `--local-project-ids`) | Fout voor dit project. Toon gevonden projecten, instrueer gebruiker om `--local-project-ids` te gebruiken. |
| `--local-project-ids` opgegeven maar aantallen kloppen niet met `--project-ids` | Validatiefout, stop. |
| `prompt_instance` delete faalt | Rollback transactie voor dit project, fout rapporteren, ga door. |
| Insert mislukt (bijv. unique constraint) | Rollback transactie voor dit project, fout rapporteren, ga door. |
| Gelinkt project bestaat niet lokaal | `project_linked_project` record overslaan, waarschuwing in rapport. |
| Template refereert globaal veld dat niet lokaal bestaat (zonder `--include-global-fields`) | Waarschuwing. Template wordt geladen maar placeholder verwijst naar niet-bestaand veld. |
| Template refereert extern veld (EXT) dat niet lokaal bestaat | Waarschuwing met detail (veldnaam, bronproject). Placeholder behouden met dump-ID. |
| Temp schema met zelfde PID bestaat al (onwaarschijnlijk) | Droppen en opnieuw aanmaken. |
| Dump bevat `USE`, `CREATE DATABASE` of `DROP DATABASE` statements | Alle drie worden gestript voor import (§3.3 stap 3a). |
| Meerdere projecten in dump met dezelfde naam | Geen conflict — selectie is op ID, niet op naam. |
| Geladen project heeft zelfde naam als een ANDER lokaal project (niet de match) | Kan voorkomen als de gebruiker lokaal een project hernoemd heeft. Bij expliciete matching (`--local-project-ids`) is dit geen probleem. Bij naamsmatching kan dit leiden tot meerdere projecten met dezelfde naam — dat is acceptabel. |
| Dump is van een oudere schema-versie (ontbrekende kolommen) | Schema-validatie in stap 4 detecteert dit. Ontbrekende kolommen worden NULL, waarschuwing getoond. Dynamische kolom-detectie handelt dit automatisch af. |
| Groot dump-bestand (>100MB) | Waarschuwing getoond. Performance hangt af van MySQL import-snelheid. Geen specifieke optimalisatie nodig voor v1. |
| `prompt_instance.final_prompt` bevat machine-specifieke bestandspaden | Bekende beperking. Prompt instances zijn snapshots — paden uit file/directory velden zijn al ingevuld en verwijzen mogelijk naar paden die lokaal niet bestaan. Geen actie nodig. |
| Globale scratch pads (`project_id = NULL`) in de dump | Worden **niet** geladen — alleen project-gebonden scratch pads. Globale scratch pads zijn niet project-scoped. |
| Process crash na aanmaken temp schema | Orphaned schemas worden opgeruimd bij volgende run (>1 uur oud) of via `project-load/cleanup`. |
| EXT-placeholder verwijst naar project waarvan label ontbreekt (NULL) in dump of lokaal | Fallback naar `name + user_id` matching. Als meerdere matches of geen match: bewaar origineel ID + waarschuwing. |
| Gedeeltelijk succes (project A OK, project B faalt) | Eindrapport vermeldt expliciet welke projecten succesvol en welke gefaald. Gebruiker wordt gewaarschuwd. |

---

## 7. Niet in scope

| Onderdeel | Reden |
|-----------|-------|
| Export-commando | Gebruiker maakt zelf een `mysqldump`. Standaard tooling volstaat. |
| Web-UI voor project load | CLI-only. Complexiteit van file uploads + lange operaties rechtvaardigt geen web-interface. |
| Bidirectionele sync | Wordt vervangen, niet geëvolueerd. |
| Soft-delete infrastructuur | Niet nodig — load doet hard-delete + volledige insert. |
| UUID kolommen | Niet nodig — matching via `--local-project-ids` of naam+user_id volstaat. |
| HTTP API / peer management | Niet nodig — dump-bestand is het transport. Geen multi-user infra, geen token-auth. Service-laag kan later achter een API gezet worden als de behoefte verandert. |
| Conflict resolution | Niet nodig — load is een volledige vervanging (delete + insert), geen merge. |
| `claude_options` / `claude_context` laden | Machine-specifiek. Moet per omgeving geconfigureerd worden. |
| `root_directory` laden | Machine-specifiek. Moet per omgeving geconfigureerd worden. |
| Laden van `user_preference` data | User-scoped, niet project-scoped. |
| Automatische Claude workspace sync na load | `Project::afterSave()` wordt niet getriggerd bij raw insert. Omdat `claude_options`/`claude_context` op NULL staan na load, is er niets te syncen. Workspace moet handmatig geconfigureerd worden na load. |

---

## 8. Verwijderde code

**Implementatievolgorde:** De verwijdering van sync-code is een **aparte stap**, uit te voeren nadat de nieuwe project-load functionaliteit volledig werkt en getest is. Dit vergemakkelijkt rollback als er problemen zijn.

### Stap 1: Nieuwe functionaliteit (dit document)
Implementeer `ProjectLoadController` en bijbehorende services.

### Stap 2: Sync-code verwijdering (apart)
**Criterium:** Verwijdering is veilig nadat de project-load functionaliteit succesvol is gebruikt voor minimaal 2 load-cycli (heen en terug tussen omgevingen) zonder dataloss.

De volgende bestanden en configuratie worden verwijderd:

| Bestand | Reden |
|---------|-------|
| `yii/services/sync/SyncService.php` | Vervangen door load-functionaliteit |
| `yii/services/sync/EntitySyncer.php` | Niet meer nodig |
| `yii/services/sync/EntityDefinitions.php` | Niet meer nodig |
| `yii/services/sync/RecordFetcher.php` | Niet meer nodig |
| `yii/services/sync/ConflictResolver.php` | Niet meer nodig |
| `yii/services/sync/RemoteConnection.php` | Niet meer nodig |
| `yii/services/sync/SyncReport.php` | Niet meer nodig |
| `yii/commands/SyncController.php` | Vervangen door nieuwe `ProjectLoadController` |

De `sync`-configuratie in `params.php` (`remoteHost`, `remoteUser`, etc.) kan ook verwijderd worden.

---

## 9. Aannames

| # | Aanname | Impact als onjuist |
|---|---------|---------------------|
| 1 | Dump-formaat is standaard `mysqldump` output (SQL met CREATE TABLE + INSERT). Verwacht commando: `mysqldump --no-create-db --skip-add-locks --skip-lock-tables yii > dump.sql` | Import naar temp schema mislukt |
| 2 | MySQL-user heeft `CREATE DATABASE` / `DROP DATABASE` rechten | Temp schema kan niet aangemaakt worden |
| 3 | `mysqldump` is uitgevoerd **zonder** `--databases` flag (geen USE-statements) | USE-statements worden gestript, maar CREATE DATABASE-statements kunnen tot verwarring leiden |
| 4 | Enkele gebruiker per machine (`--user-id` is consistent) | Data wordt aan verkeerde gebruiker gekoppeld |
| 5 | Projectnamen zijn **niet** uniek per gebruiker (geen DB constraint, geen model validatie). Matching-strategie in §3.3.1 houdt hier rekening mee. | N.v.t. — opgelost door matching-strategie met `--local-project-ids` fallback |
| 6 | `prompt_instance.template_id` FK is ON DELETE RESTRICT. **NB:** De initiële migratie (`m230101_000001`) maakt FK `fk_prompt_instance_template` aan met RESTRICT. Migratie `m250610_000002` controleert via `INFORMATION_SCHEMA` of er al een FK op `template_id → prompt_template` bestaat — zo ja, slaat de migratie het aanmaken over (idempotent). Op bestaande databases is het resultaat daarom altijd RESTRICT. **Verifieer in de doelomgeving met `SHOW CREATE TABLE prompt_instance`.** | Als FK toch CASCADE is, zijn de expliciete deletes in §3.3 stap 5g overbodig maar niet schadelijk. Veiligheidshalve altijd expliciet verwijderen. |
| 7 | `mysql` CLI binary is beschikbaar in de Docker-container (`pma_yii`). | Dump-import via `proc_open()` faalt. Alternatief: PDO-gebaseerde import (§5.1). |

---

## 10. Tests

### 10.1 Integrity-test: kolom-configuratie

Een unit test die verifieert dat de dynamische kolom-detectie correct werkt met de huidige schema-staat:

```php
public function testExcludedColumnsExistInSchema(): void
// Verifieert dat alle kolommen in de exclude-lijst (§3.9) daadwerkelijk bestaan
// in het productie-schema. Vangt hernoemde/verwijderde kolommen op.

public function testOverrideColumnsExistInSchema(): void
// Verifieert dat kolommen met override (user_id) bestaan in de verwachte tabellen.

public function testForeignKeyColumnsExistInSchema(): void
// Verifieert dat alle FK-kolommen uit de entiteiten-configuratie (§3.5.2)
// bestaan in het productie-schema.
```

**Rationale:** De dynamische aanpak elimineert verouderde kolomlijsten, maar de excludes, overrides en FK-configuratie zijn nog steeds handmatig. Deze tests vangen fouten op als die configuratie niet meer klopt na een migratie.

### 10.2 Functionele test-scenario's

| Scenario | Wat te verifiëren |
|----------|-------------------|
| Load nieuw project (geen lokale match) | Project + alle children correct geïnserteerd, nieuw ID toegewezen |
| Load bestaand project (naam-match) | Oud project + children verwijderd, dump-data met behouden ID geïnserteerd |
| Load met `--local-project-ids` | Expliciete matching werkt, correct ID behouden |
| Load met `--include-global-fields` | Globale velden aangemaakt of hergebruikt, placeholders correct geremapped |
| Placeholder remapping PRJ | Nieuwe field-IDs correct in template_body |
| Placeholder remapping GEN | Lokaal bestaande globale velden correct gematcht |
| Placeholder remapping EXT | Externe velden via label+user_id correct geresolved |
| Dry-run | Geen data-wijzigingen, rapport correct |
| Soft-deleted project in dump | Overgeslagen met waarschuwing |
| Meerdere naam-matches | Foutmelding met instructie voor `--local-project-ids` |
| Ontbrekende kolom in dump (oudere versie) | NULL voor ontbrekende kolom, waarschuwing |
| Gelinkt project bestaat niet lokaal | Link overgeslagen, waarschuwing |
| Transactie-rollback bij insert-fout | Geen gedeeltelijke data, volgend project wordt wél geprobeerd |

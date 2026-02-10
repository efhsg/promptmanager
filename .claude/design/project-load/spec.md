# Specificatie: Project Load uit Database Dump

## Metadata

| Eigenschap | Waarde |
|------------|--------|
| Versie | 1.1 |
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
```

### 3.2 Flow: `project-load/list`

```
1. Valideer dump-bestand (bestaat, leesbaar, .sql extensie)
2. Maak tijdelijk MySQL-schema (yii_load_temp)
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
   d. Als --local-project-ids opgegeven: zelfde aantal als --project-ids, geldige integers, verwijzen naar bestaande projecten van user

2. Maak tijdelijk MySQL-schema (yii_load_temp)

3. Importeer dump in tijdelijk schema
   a. Strip "USE ..." regels uit dump (voorkom schrijven naar productie-schema)
   b. mysql yii_load_temp < (gefilterde dump)

4. Voor elk gevraagd project-ID:
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
      - Verwijder prompt_instances (ON DELETE RESTRICT vereist expliciete delete)
      - Verwijder lokaal project ($model->delete() — CASCADE verwijdert rest)
   h. Insert project met nieuwe auto-increment ID
   i. Insert children in afhankelijkheidsvolgorde (§3.6), remap FK-referenties
   j. Remap placeholder-IDs in template_body (§3.7)
   k. Commit transactie (of rollback bij fout)

5. Verwijder tijdelijk schema

6. Toon eindrapport (per project: succes/fout + statistieken)
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
    ℹ root_directory, claude_options, claude_context worden niet geladen
      (machine-specifiek) — configureer na het laden.

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

### 3.5 Entiteiten per project

Entiteiten die geladen worden voor elk project, met expliciete kolomlijsten. Alle kolommen worden meegenomen tenzij uitgesloten in §3.9.

| Entiteit | Bron-query (temp schema) | Kolommen | Opmerkingen |
|----------|--------------------------|----------|-------------|
| `project` | `WHERE id = :projectId` | name, description, allowed_file_extensions, blacklisted_directories, prompt_instance_copy_format, label, deleted_at, created_at, updated_at | Root entiteit. Kolommen uit §3.9 worden NULL. |
| `context` | `WHERE project_id = :projectId` | project_id, name, content, is_default, share, order, created_at, updated_at | Quill Delta content. Alle attributen inclusief volgorde en share-status. |
| `field` | `WHERE project_id = :projectId` | user_id, project_id, name, type, content, share, label, render_label, created_at, updated_at | Project-gebonden velden met alle presentatie-attributen. |
| `field_option` | `WHERE field_id IN (:projectFieldIds)` | field_id, value, label, selected_by_default, order, created_at, updated_at | Opties van project-velden inclusief volgorde en defaults. |
| `prompt_template` | `WHERE project_id = :projectId` | project_id, name, template_body, created_at, updated_at | Template body = Quill Delta met placeholder-IDs. |
| `template_field` | `WHERE template_id IN (:projectTemplateIds)` | template_id, field_id, order, override_label, created_at, updated_at | Pivot tabel inclusief volgorde en label-overschrijvingen. |
| `prompt_instance` | `WHERE template_id IN (:projectTemplateIds)` | template_id, label, final_prompt, created_at, updated_at | Gegenereerde prompts. `final_prompt` is gerenderde output, geen remapping nodig (§3.7.1). |
| `scratch_pad` | `WHERE project_id = :projectId` | user_id, project_id, name, content, response, created_at, updated_at | Inclusief response kolom (ontbrak in oude sync). |
| `project_linked_project` | `WHERE project_id = :projectId` | project_id, linked_project_id, created_at, updated_at | Eenrichtings: alleen links vanuit dit project. Alleen als gelinkt project lokaal bestaat (§3.5.1). |

**Optioneel met `--include-global-fields`:**

| Entiteit | Bron-query | Kolommen | Opmerkingen |
|----------|------------|----------|-------------|
| `field` (globaal) | `WHERE project_id IS NULL AND id IN (:referencedGlobalFieldIds)` | Zelfde als field hierboven | Alleen velden gerefereerd door dit project's templates |
| `field_option` (globaal) | `WHERE field_id IN (:globalFieldIds)` | Zelfde als field_option hierboven | Opties van geladen globale velden |

### 3.5.1 Globale velden: match-en-hergebruik strategie

Globale velden worden gematcht op `name + user_id`:

- **Bestaat lokaal:** het **lokale veld behouden** en het lokale ID gebruiken voor remapping. Het globale veld wordt **niet** overschreven, omdat andere lokale projecten/templates ernaar kunnen verwijzen. Waarschuwing in rapport als type of aantal opties verschilt.
- **Bestaat niet lokaal:** nieuw aanmaken met data uit de dump.

**Rationale:** Globale velden zijn per definitie gedeeld tussen projecten. Overschrijven met dump-data kan onbedoeld content wijzigen die door andere lokale projecten wordt gebruikt.

### 3.5.2 Project links: eenrichtingsmodel

`project_linked_project` is een eenrichtingsrelatie: een record `(project_id=A, linked_project_id=B)` betekent dat project A linkt naar project B. Er wordt **geen** omgekeerd record `(B, A)` aangemaakt. Bij het laden worden alleen links **vanuit** het geladen project meegenomen.

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

`template_field` moet na zowel `prompt_template` als `field` omdat het naar beide verwijst.

### 3.7 Placeholder-ID remapping

`template_body` bevat placeholders als `PRJ:{{42}}`, `GEN:{{15}}`, `EXT:{{8}}` waar de nummers field-IDs zijn uit de brondata. Bij het laden veranderen deze IDs.

**Remapping-strategie per placeholder-type:**

| Type | Bron | Remapping |
|------|------|-----------|
| `PRJ:{{id}}` | Project-veld, mee geladen | Gebruik ID mapping van geladen velden |
| `GEN:{{id}}` | Globaal veld | Als geladen (--include-global-fields): gebruik mapping. Anders: zoek lokaal veld met zelfde naam, gebruik lokaal ID. Als niet gevonden: bewaar origineel ID + waarschuwing |
| `EXT:{{id}}` | Veld uit ander project | Zoek veld in temp schema → bepaal naam + project → zoek lokaal project (naam+user_id) → zoek lokaal veld (naam+project_id) → gebruik lokaal ID. Als niet gevonden: bewaar origineel ID + waarschuwing |

**Regex voor remapping:**

```
/(GEN|PRJ|EXT):\{\{(\d+)\}\}/
```

Dit lost ook de bestaande sync-bug op (placeholder-IDs werden niet geremapped).

### 3.7.1 `prompt_instance.final_prompt` — geen remapping

`prompt_instance.final_prompt` bevat de **gerenderde output**: veld-waarden zijn al ingevuld door `PromptGenerationService` op het moment van generatie. Dit is Quill Delta JSON met concrete content, geen placeholder-referenties. Er is daarom **geen** ID-remapping nodig voor deze kolom.

**`template_field` records:**

Template_field records koppelen template_id aan field_id. Na remapping van beide IDs:
- Als field_id verwijst naar een geladen project-veld: gebruik geremapte ID
- Als field_id verwijst naar een globaal veld: gebruik geremapte of lokale ID
- Als field_id verwijst naar een extern veld: zoek lokaal ID
- Als veld niet gevonden: template_field record overslaan + waarschuwing

### 3.8 User-ID afhandeling

- Alle geladen data krijgt de lokale `--user-id` (standaard: 1)
- De user_id uit de dump wordt **niet** overgenomen
- Dit geldt voor: `project.user_id`, `field.user_id`, `scratch_pad.user_id`

### 3.9 Kolommen die NIET geladen worden

| Kolom | Reden |
|-------|-------|
| `project.claude_options` | Machine-specifiek (Claude CLI configuratie) |
| `project.claude_context` | Machine-specifiek (Quill Delta met project-instructies) |
| `project.root_directory` | Machine-specifiek (bestandspad) |

Deze kolommen worden op `NULL` gezet bij het laden. Als het project al lokaal bestond, gaan deze waarden verloren — dat is de gewenste situatie (ze moeten per machine geconfigureerd worden).

**Let op:** `project.root_directory` is machine-specifiek en moet na het laden opnieuw geconfigureerd worden. Bij de dry-run wordt dit als melding getoond.

---

## 4. Bestaande infrastructuur

| Component | Pad | Relevantie |
|-----------|-----|------------|
| `EntityDefinitions` | `yii/services/sync/EntityDefinitions.php` | Kolom-definities per entiteit. Herbruikbaar als referentie, maar ontbreekt `scratch_pad.response` en project kolommen `claude_options`, `claude_context`. |
| `RecordFetcher` | `yii/services/sync/RecordFetcher.php` | Query-patronen per entiteit met user-scoping. Conceptueel herbruikbaar voor het ophalen uit temp schema, maar scoping is anders (per project-ID, niet per user). |
| `EntitySyncer` | `yii/services/sync/EntitySyncer.php` | FK remapping via `mapForeignKeys()` en `SyncReport.idMappings`. Concept herbruikbaar, maar implementatie te verweven met bidirectionele sync-logica. |
| `SyncReport` | `yii/services/sync/SyncReport.php` | ID mapping opslag + rapportage. Concept herbruikbaar voor nieuwe `LoadReport`. |
| `SyncController` | `yii/commands/SyncController.php` | CLI output formatting (kleuren, statistieken). Pattern voor nieuwe controller. |
| `Project::afterDelete()` | `yii/models/Project.php:584` | Ruimt Claude workspace op via `claudeWorkspaceService->deleteWorkspace()`. Wordt automatisch aangeroepen bij `$model->delete()`. **Bij project-vervanging:** de workspace van het oude project wordt verwijderd. Dit is gewenst — het nieuwe project krijgt toch `claude_options`/`claude_context` op NULL (§3.9), dus de workspace moet per machine opnieuw geconfigureerd worden. |
| `Project::afterSave()` | `yii/models/Project.php:560` | Synct Claude workspace config bij insert of relevante field changes. Wordt NIET aangeroepen bij raw insert (wat wij doen). Na insert moet `claudeWorkspaceService->syncConfig()` apart aangeroepen worden als dit gewenst is. Omdat `claude_options` en `claude_context` op NULL staan na load, is workspace-sync optioneel (er is niets te syncen). |
| `TimestampTrait` | `yii/models/traits/TimestampTrait.php` | Timestamp-afhandeling. Niet relevant — we kopiëren timestamps uit de dump. |

---

## 5. Toegangscontrole

- **Uitsluitend CLI** — geen web-UI, geen RBAC nodig
- Console commands vallen buiten Yii2 AccessControl behaviors
- `--user-id` parameter bepaalt eigenaar van geladen data
- Bestandstoegang: dump-bestand moet leesbaar zijn voor de PHP-procesuser
- MySQL-user moet `CREATE DATABASE` en `DROP DATABASE` rechten hebben voor het tijdelijk schema

### 5.1 Bestandspad-sanitization

Het dump-bestandspad wordt als argument aan een shell-commando doorgegeven (`mysql ... < bestand`). Om shell injection te voorkomen:

- Valideer het pad via `realpath()` — het bestand moet daadwerkelijk bestaan
- Valideer extensie (`.sql` of `.dump`)
- Gebruik `escapeshellarg()` voor het pad in het shell-commando
- Voer mysql uit via `proc_open()` of Yii2's `Process`, **niet** via `shell_exec()` of backticks
- Geen user-input in de SQL die naar het temp-schema gestuurd wordt (schema-naam is hardcoded)

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
| Meerdere lokale projecten met zelfde naam (zonder `--local-project-ids`) | Fout voor dit project. Toon gevonden projecten, instrueer gebruiker om `--local-project-ids` te gebruiken. |
| `--local-project-ids` opgegeven maar aantallen kloppen niet met `--project-ids` | Validatiefout, stop. |
| `prompt_instance` delete faalt | Rollback transactie voor dit project, fout rapporteren, ga door. |
| Insert mislukt (bijv. unique constraint) | Rollback transactie voor dit project, fout rapporteren, ga door. |
| Gelinkt project bestaat niet lokaal | `project_linked_project` record overslaan, waarschuwing in rapport. |
| Template refereert globaal veld dat niet lokaal bestaat (zonder `--include-global-fields`) | Waarschuwing. Template wordt geladen maar placeholder verwijst naar niet-bestaand veld. |
| Template refereert extern veld (EXT) dat niet lokaal bestaat | Waarschuwing. Placeholder behouden met dump-ID. |
| Temp schema `yii_load_temp` bestaat al | Droppen en opnieuw aanmaken. |
| Dump bevat `USE` of `CREATE DATABASE` statements | `USE`-statements worden gestript voor import. `CREATE DATABASE` genegeerd (schema al aangemaakt). |
| Meerdere projecten in dump met dezelfde naam | Geen conflict — selectie is op ID, niet op naam. |
| Geladen project heeft zelfde naam als een ANDER lokaal project (niet de match) | Kan voorkomen als de gebruiker lokaal een project hernoemd heeft. Bij expliciete matching (`--local-project-ids`) is dit geen probleem. Bij naamsmatching kan dit leiden tot meerdere projecten met dezelfde naam — dat is acceptabel. |
| Dump is van een oudere schema-versie (ontbrekende kolommen) | Ontbrekende kolommen worden NULL. Waarschuwing als verwachte kolommen ontbreken. |
| Groot dump-bestand (>100MB) | Performance hangt af van MySQL import-snelheid. Geen specifieke optimalisatie nodig voor v1. |

---

## 7. Niet in scope

| Onderdeel | Reden |
|-----------|-------|
| Export-commando | Gebruiker maakt zelf een `mysqldump`. Standaard tooling volstaat. |
| Web-UI voor project load | CLI-only. Complexiteit van file uploads + lange operaties rechtvaardigt geen web-interface. |
| Bidirectionele sync | Wordt vervangen, niet geëvolueerd. |
| Soft-delete infrastructuur | Niet nodig — load doet hard-delete + volledige insert. |
| UUID kolommen | Niet nodig — matching via `--local-project-ids` of naam+user_id volstaat. |
| HTTP API / peer management | Niet nodig — dump-bestand is het transport. |
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
| 1 | Dump-formaat is standaard `mysqldump` output (SQL met CREATE TABLE + INSERT) | Import naar temp schema mislukt |
| 2 | MySQL-user heeft `CREATE DATABASE` / `DROP DATABASE` rechten | Temp schema kan niet aangemaakt worden |
| 3 | `mysqldump` is uitgevoerd **zonder** `--databases` flag (geen USE-statements) | USE-statements worden gestript, maar CREATE DATABASE-statements kunnen tot verwarring leiden |
| 4 | Enkele gebruiker per machine (`--user-id` is consistent) | Data wordt aan verkeerde gebruiker gekoppeld |
| 5 | Projectnamen zijn **niet** uniek per gebruiker (geen DB constraint, geen model validatie). Matching-strategie in §3.3.1 houdt hier rekening mee. | N.v.t. — opgelost door matching-strategie met `--local-project-ids` fallback |
| 6 | `prompt_instance.template_id` FK is ON DELETE RESTRICT. **NB:** De initiele migratie (`m230101_000001`) zet deze FK als RESTRICT, maar migratie `m250610_000002` probeert een CASCADE FK toe te voegen. Op bestaande databases is deze migratie een no-op omdat de FK al bestaat. **Verifieer in de doelomgeving met `SHOW CREATE TABLE prompt_instance`.** | Als FK toch CASCADE is, zijn de expliciete deletes in §3.3 stap 4g overbodig maar niet schadelijk. Veiligheidshalve altijd expliciet verwijderen. |

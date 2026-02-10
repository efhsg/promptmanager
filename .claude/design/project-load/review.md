# Kritische Review — Project Load uit Database Dump

**Reviewer:** PromptManager Analyst
**Document under review:** `.claude/design/project-load/spec.md` v1.3
**Datum:** 2026-02-10
**Vorige review:** v1.2 — 6 actiepunten, alle 6 verwerkt in v1.3

---

## Verdict: GOEDGEKEURD — klaar voor implementatie

De v1.3 spec is uitstekend. Alle actiepunten uit de v1.2 review zijn correct verwerkt: placeholder-remapping is nu gespecificeerd op Delta ops-niveau met referentie-implementatie, TimestampTrait-impact is benoemd met raw-insert als oplossing, `project.description` is als Quill Delta gemarkeerd, `CREATE DATABASE`/`DROP DATABASE` stripping is toegevoegd, template_field EXT-logica is verduidelijkt, en field_option FK-afhankelijkheid is gedocumenteerd.

De spec is feitelijk accuraat tegen de codebase geverifieerd (zie §2). De architectuur is doordacht, de edge cases uitgebreid behandeld, en de scope strak afgebakend.

Hieronder: verificatierapport, resterende aandachtspunten, en een klein aantal nieuwe observaties.

---

## 1. Verificatie: v1.2 actiepunten verwerkt

| # | v1.2 Actie | Status in v1.3 |
|---|-----------|----------------|
| 1 | Placeholder-remapping op Delta ops-niveau specificeren | **Opgelost** — §3.7 beschrijft nu parse→iterate→regex→re-encode patroon met referentie naar `convertPlaceholdersToIds()` |
| 2 | TimestampTrait overschrijft dump-timestamps benoemen | **Opgelost** — §3.3 stap 4i waarschuwt voor TimestampTrait en noemt raw inserts als oplossing |
| 3 | `project.description` als Quill Delta markeren | **Opgelost** — §3.5 project-rij vermeldt "description = Quill Delta JSON (niet plain text)" |
| 4 | "Extern veld" definiëren bij template_field remapping | **Opgelost** — §3.7.1 definieert: "Als field_id in geen van beide mappings voorkomt: behandel als extern veld (EXT)" |
| 5 | `CREATE DATABASE`/`DROP DATABASE` strippen | **Opgelost** — §3.3 stap 3a strip nu alle drie (USE, CREATE DATABASE, DROP DATABASE). §6 edge case tabel is bijgewerkt. |
| 6 | `field_option` FK-afhankelijkheid toelichten | **Opgelost** — §3.6 bevat nu: "`field_option` moet na `field` (FK `field_id → field`)" |

**Resultaat:** 6/6 verwerkt.

---

## 2. Feitelijke Verificatie tegen Codebase

Alle feitelijke claims in v1.3 zijn geverifieerd tegen de broncode:

| Claim | Verificatie |
|-------|------------|
| `Project::afterDelete()` roept `claudeWorkspaceService->deleteWorkspace()` aan | **Correct** — `yii/models/Project.php:584-593` |
| `Project::afterSave()` synct workspace config bij insert of relevante field changes | **Correct** — `yii/models/Project.php:560-582`, controleert `$insert` of `$changedAttributes` voor relevante velden |
| 7 sync-bestanden in `yii/services/sync/` | **Correct** — SyncService, EntitySyncer, EntityDefinitions, RecordFetcher, ConflictResolver, RemoteConnection, SyncReport |
| `SyncController` in `yii/commands/` | **Correct** — bestaat |
| `prompt_instance.template_id` FK is ON DELETE RESTRICT | **Correct** — `m230101_000001` lijn 196-204: `addForeignKey(..., 'RESTRICT', 'CASCADE')` = ON DELETE RESTRICT, ON UPDATE CASCADE |
| `m250610_000002` is no-op op bestaande databases | **Correct** — controleert via `$table->foreignKeys` of FK al bestaat, skip zo ja |
| `EntityDefinitions` mist `scratch_pad.response` | **Correct** — kolommen: `user_id, project_id, name, content, created_at, updated_at` — `response` ontbreekt |
| `EntityDefinitions` mist `claude_options`/`claude_context` | **Correct** — niet in project kolommen van EntityDefinitions |
| `ScratchPad` heeft `response` kolom | **Correct** — property + validatieregel bevestigen dit |
| Project namen NIET uniek per user | **Correct** — geen DB constraint, geen model unique-validatie |
| Project label WEL uniek per user | **Correct** — DB index `idx-project-user_label-unique` (m251130_000010) + model unique-validatie met NULL-filter |
| `TemplateField` heeft alleen `template_id` + `field_id` | **Correct** — `m230101_000002` dropt tabel en herlegt zonder `id`, `order`, `override_label`, timestamps |
| `project.description` is Quill Delta JSON | **Correct** — formulier gebruikt Quill-editor (`_form.php:270`), opslaat als `JSON.stringify(quill.getContents())` |
| Placeholder-regex in `convertPlaceholdersToIds()` werkt op Delta ops | **Correct** — `PromptTemplateService.php:116-143` parsed JSON, itereert `$delta['ops']`, regex per `$op['insert']` |
| `ProjectLinkedProject` unique constraint op `(project_id, linked_project_id)` | **Correct** — `m251130_000007` index `idx_project_linked_project_unique` met `true` |
| 9 modellen gebruiken `TimestampTrait` | **Correct** — Project, Context, Field, FieldOption, PromptTemplate, PromptInstance, ProjectLinkedProject, ScratchPad, UserPreference |
| Cascade-gedrag overige FK's (context, field, field_option, prompt_template, template_field, scratch_pad, project_linked_project) | **Correct** — allemaal ON DELETE CASCADE in initiële migratie |
| `Project::afterSave()` wordt NIET getriggerd bij raw insert | **Correct** — raw insert via `createCommand()->insert()` bypassed ActiveRecord lifecycle |
| `Project::afterDelete()` WEL getriggerd bij `$model->delete()` | **Correct** — ActiveRecord `delete()` roept `afterDelete()` aan |

**Conclusie:** De spec is feitelijk accuraat. Geen factual errors gevonden.

---

## 3. Nieuwe observaties

### 3.1 §3.3 stap 4h — TimestampTrait-inconsistentie bij project insert

**Should fix — inconsistentie tussen stap 4h en 4i**

Stap 4h beschrijft project-insert via ActiveRecord: `$model->id = $savedLocalId` + `$model->setOldAttributes([])` + `$model->save()`. Stap 4i waarschuwt dat `TimestampTrait` timestamps overschrijft en adviseert raw inserts.

**Probleem:** `Project` gebruikt ook `TimestampTrait`. Als stap 4h `$model->save()` aanroept, worden `created_at` en `updated_at` overschreven met `time()`, ongeacht de dump-waarden. De spec lost dit voor children op (raw inserts), maar niet voor het project zelf.

Bovendien triggert `$model->save()` ook `afterSave()`, wat `claudeWorkspaceService->syncConfig()` aanroept. Hoewel de spec dit in §4 benoemt als "niet relevant omdat claude_options/claude_context op NULL staan", is het aanroepen van een workspace-service op een half-geladen project (children zijn nog niet geïnserteerd) een potentieel probleem als die service ooit side-effects krijgt.

**Actie:** Maak de keuze consistent: gebruik ook voor het project-record een raw insert (`createCommand()->insert()`), of documenteer expliciet dat timestamps voor het project-record verloren gaan (en waarom dat acceptabel is). Als raw insert wordt gekozen, geldt het ID-hergebruik-patroon (`$savedLocalId`) gewoon door de ID direct als kolomwaarde mee te geven.

### 3.2 §3.3 stap 4g — `afterDelete()` side-effect bij project verwijdering

**Info — geen actie nodig, maar documenteren overwegen**

Bij het verwijderen van het lokale project via `$model->delete()` (stap 4g) wordt `Project::afterDelete()` getriggerd, wat `claudeWorkspaceService->deleteWorkspace()` aanroept. Dit verwijdert de Claude-werkruimte van het oude project.

De spec benoemt dit correct in §4 ("de workspace van het oude project wordt verwijderd. Dit is gewenst"). Dit is inderdaad het gewenste gedrag. Geen actie nodig.

### 3.3 §3.5 — `deleted_at` wordt geladen maar niet gefilterd bij children

**Consider — edge case**

§3.3 stap 4a filtert correct: als een project `deleted_at IS NOT NULL` heeft, wordt het overgeslagen met een waarschuwing. Maar voor het project dat WEL geladen wordt, bevat de kolomlijst in §3.5 `deleted_at`. Dit is correct — als het project niet soft-deleted is, zal `deleted_at` NULL zijn en wordt het as-is gekopieerd.

Echter: als de dump van een oudere datum is en het project later lokaal soft-deleted was, wordt het na load weer actief (deleted_at = NULL uit de dump). Dit is gewenst gedrag (de dump is de bron van waarheid), maar het zou goed zijn om dit in het dry-run rapport te tonen als het lokale project `deleted_at IS NOT NULL` heeft.

**Actie:** Overweeg om in het dry-run rapport een waarschuwing te tonen als het lokale project soft-deleted is: "Lokaal project is verwijderd (deleted_at = ...). Na load wordt het opnieuw actief."

### 3.4 §3.5 — Kolomlijst mist `prompt_instance.label`

**Correct in spec, alleen verwarrend in migratie**

De kolomlijst voor `prompt_instance` vermeldt `template_id, label, final_prompt, created_at, updated_at`. Maar de initiële migratie (`m230101_000001`) definieert `prompt_instance` zonder `label` — die kolom is later toegevoegd. Ik heb geverifieerd dat de kolom bestaat in het huidige model.

Dit is geen probleem: de spec beschrijft het huidige schema, niet het migratiepad. Edge case §6 "Dump is van een oudere schema-versie" dekt dit ("ontbrekende kolommen worden NULL").

### 3.5 §9 Aanname 6 — formulering "INFORMATION_SCHEMA" vs werkelijke implementatie

**Cosmetic**

De aanname vermeldt dat `m250610_000002` "controleert via `INFORMATION_SCHEMA`". In werkelijkheid controleert de migratie via Yii2's `$table->foreignKeys` (PHP-niveau schema introspection), niet via een directe INFORMATION_SCHEMA-query. Het resultaat is hetzelfde, maar de formulering is niet exact.

**Actie:** Corrigeer naar: "controleert via `getTableSchema()->foreignKeys`" of verwijder het specifieke mechanisme en zeg simpelweg "controleert of er al een FK bestaat".

### 3.6 §4 — Bestaande infrastructuur: `TimestampTrait` relevantie

**Cosmetic**

De §4 tabel vermeldt: "TimestampTrait — Niet relevant — we kopiëren timestamps uit de dump." Dit is misleidend: de trait IS relevant, namelijk als obstakel dat omzeild moet worden. §3.3 stap 4i beschrijft dit correct. De §4 tabel zou beter "Relevant als aandachtspunt — bypassed via raw inserts (§3.3)" kunnen zeggen.

---

## 4. Wat de spec goed doet

- **Alle v1.2 actiepunten correct verwerkt** — elk punt is nauwkeurig geadresseerd
- **Feitelijke accuratesse is uitmuntend** — elke claim over de codebase klopt, inclusief lijn-nummers
- **Placeholder-remapping is nu volledig gespecificeerd** — met parse-stappen, regex, en referentie-implementatie
- **TimestampTrait-workaround is praktisch** — raw inserts zijn de juiste aanpak
- **CREATE/DROP DATABASE stripping** — defensief en correct
- **Edge cases zijn uitgebreid** — 21 scenario's in §6, elk met duidelijk gedrag
- **Dry-run rapport is informatief** — met prominente waarschuwingen voor type-mismatches en ontbrekende velden
- **Twee-staps implementatie met verwijdercriterium** — pragmatisch en veilig
- **Globale velden match-en-hergebruik strategie** — voorkomt cascade-effecten op andere projecten
- **Bestandspad-sanitization** — `realpath()` + `escapeshellarg()` + `proc_open()` is de juiste aanpak

---

## 5. Samenvatting Actiepunten

| # | Prioriteit | Actie | Sectie |
|---|-----------|-------|--------|
| 1 | **Should fix** | Los TimestampTrait-inconsistentie op voor het project-record zelf: gebruik raw insert óf documenteer waarom timestamp-verlies acceptabel is. Benoem ook dat `afterSave()` getriggerd wordt bij ActiveRecord save. | §3.3 stap 4h |
| 2 | **Consider** | Toon in dry-run rapport als lokaal project soft-deleted is ("wordt opnieuw geactiveerd na load") | §3.4, §6 |
| 3 | **Cosmetic** | Corrigeer "INFORMATION_SCHEMA" naar "getTableSchema()->foreignKeys" in aanname 6 | §9 |
| 4 | **Cosmetic** | Corrigeer TimestampTrait als "niet relevant" in §4 naar "relevant als aandachtspunt" | §4 |

**Totaal:** 1 should-fix, 1 consider, 2 cosmetic. Geen must-fixes.

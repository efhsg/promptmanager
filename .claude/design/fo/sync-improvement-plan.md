# Sync System Improvement — Final Specification

## Metadata

| Property | Value |
|----------|-------|
| Version | 1.0 |
| Status | Final Draft |
| Date | 2026-02-10 |

---

## Table of Contents

1. [Situation](#1-situation)
2. [Architecture Overview](#2-architecture-overview)
3. [FO Corrections Required](#3-fo-corrections-required)
4. [Design Decisions](#4-design-decisions)
5. [Placeholder Integrity Problem](#5-placeholder-integrity-problem)
6. [FK Cascade Map](#6-fk-cascade-map)
7. [Phase 1: Schema Foundation](#7-phase-1-schema-foundation)
8. [Phase 2a: Soft-Delete Foundation](#8-phase-2a-soft-delete-foundation)
9. [Phase 2b: Sync Enhancement](#9-phase-2b-sync-enhancement)
10. [Phase 3: HTTP API](#10-phase-3-http-api)
11. [What Does Not Change](#11-what-does-not-change)
12. [Risks and Mitigations](#12-risks-and-mitigations)
13. [Assumptions and Constraints](#13-assumptions-and-constraints)

---

## 1. Situation

Two artifacts address bidirectional sync between PromptManager instances:

| Artifact | Status | Approach |
|----------|--------|----------|
| `yii/services/sync/` (7 classes + CLI) | Working code, ~500 LOC | Direct MySQL over SSH tunnel, natural key matching |
| `sync-api-design.md` (FO v1.5) | Draft specification | HTTP REST API, UUID-based identity, soft deletes |

The FO does not acknowledge the existing implementation. The existing implementation lacks deletion sync, has natural-key fragility, and is SSH-coupled. Both have value; neither is complete.

### Existing Sync Architecture

```
SyncController (CLI)
  └─► SyncService::run(userId, dryRun)
        ├─► RemoteConnection::connect()           # SSH tunnel + MySQL
        ├─► sync(remoteDb, localDb, userId)        # PULL: remote → local
        │     └─► EntitySyncer::syncEntity()       # For each entity in order
        │           ├─► RecordFetcher::fetch()      # Get source + dest records
        │           ├─► buildSemanticKey()           # Natural key matching
        │           ├─► ConflictResolver::resolve()  # Last-write-wins
        │           └─► insertRecord/updateRecord    # Write to dest DB
        ├─► sync(localDb, remoteDb, userId)        # PUSH: local → remote
        │     └─► (same EntitySyncer instance)     # State leakage risk
        └─► RemoteConnection::disconnect()
```

**Key classes:**

| Class | Role |
|-------|------|
| `SyncService` | Orchestrator — runs pull then push using same `EntitySyncer` instance |
| `EntitySyncer` | Core logic — semantic key matching, insert/update, FK remapping |
| `EntityDefinitions` | Schema — entity columns, natural keys, foreign keys, sync order |
| `RecordFetcher` | Data access — scoped queries per entity type, user filtering |
| `ConflictResolver` | Conflict resolution — last-write-wins, source wins on tie |
| `RemoteConnection` | Transport — SSH tunnel with dynamic port, MySQL connection |
| `SyncReport` | Results — `idMappings[entity][sourceId] => destId`, errors, stats |

### Current Sync Order

```php
EntityDefinitions::getSyncOrder():
  project → project_linked_project → context → field → field_option
  → prompt_template → template_field → scratch_pad → prompt_instance
```

### Current EntityDefinitions Columns (Verified)

| Entity | Natural Keys | FK Columns | Data Columns |
|--------|-------------|------------|-------------|
| `project` | `name, user_id` | — | `description, root_directory, allowed_file_extensions, blacklisted_directories, prompt_instance_copy_format, label, created_at, updated_at, deleted_at` |
| `context` | `name` | `project_id → project` | `content, is_default, share, order, created_at, updated_at` |
| `field` | `name, user_id` | `project_id → project` | `type, content, share, label, render_label, created_at, updated_at` |
| `field_option` | `value` | `field_id → field` | `label, selected_by_default, order, created_at, updated_at` |
| `prompt_template` | `name` | `project_id → project` | `template_body, created_at, updated_at` |
| `scratch_pad` | `name, user_id` | `project_id → project` | `content, created_at, updated_at` |
| `prompt_instance` | `label, created_at` | `template_id → prompt_template` | `final_prompt, updated_at` |
| `template_field` | — | `template_id → prompt_template, field_id → field` | *(pivot only)* |
| `project_linked_project` | — | `project_id → project, linked_project_id → project` | *(FK pair only)* |

### Known Bugs in Current Implementation

1. **Placeholder IDs not remapped.** `template_body` contains `PRJ:{{42}}` placeholders with local auto-increment field IDs. During sync, `template_field.field_id` is remapped via `mapForeignKeys()`, but `template_body` is copied verbatim. The destination has mismatched IDs between the Delta JSON and the pivot table.

2. **`scratch_pad.response` not synced.** The `response` column (Quill Delta, AI-generated content) is missing from `EntityDefinitions`. Data loss on sync.

3. **`project.claude_options` and `project.claude_context` not synced.** Missing from `EntityDefinitions`. May be intentional (machine-specific paths) but needs explicit decision.

4. **Soft-deleted projects still synced.** `RecordFetcher::getProjectIds()` does not filter `deleted_at IS NULL`. Soft-deleted projects and their children are included in sync — currently harmless because `Project.deleted_at` is never written to, but becomes a bug once soft-delete is implemented.

5. **EntitySyncer state leakage.** `SyncService::run()` creates one `EntitySyncer` instance and uses it for both pull and push. Internal state (`sourceKeyLookup`, `destKeyLookup`) from the pull pass may pollute the push pass.

### Evolution Strategy

```
Phase 0 (Now)          Phase 1               Phase 2a              Phase 2b              Phase 3
─────────────          ───────               ──────                ──────                ───────
SSH + MySQL ──────►    + UUID columns    ──► + Soft delete     ──► + Delete sync     ──► HTTP API
Natural keys           + UUID generation     + Query scopes        + Conflict log         Peer management
No delete sync         + Column fixes        + Cascade logic       + Placeholder fix      Token auth
No conflict log        + Bug #5 fix          + CRUD changes        + DeletionPropagator   Replace SSH
```

Each phase is independently deployable and testable. No phase requires the next.

---

## 2. Architecture Overview

### Entity Dependency Graph

```
user
├─► project ─────────────────────────────────────────┐
│   ├─► context                                      │
│   ├─► prompt_template ──► template_field ◄── field ─┤
│   │   └─► prompt_instance                    │     │
│   ├─► scratch_pad                            │     │
│   └─► project_linked_project ◄───────────────┘─────┘
├─► field
└─► scratch_pad
```

### Sync Data Flow

```
Machine A                          Machine B
──────────                         ──────────
1. RecordFetcher pulls all         1. RecordFetcher pulls all
   records for user_id                records for user_id

2. EntitySyncer builds             2. EntitySyncer builds
   semantic keys per entity           semantic keys per entity

3. For each entity in sync order:
   ├─ Match by semantic key
   ├─ ConflictResolver: compare updated_at
   ├─ Insert/Update with FK remapping
   └─ Store idMappings[entity][sourceId] = destId

4. PULL: remote → local (Machine A reads from B)
5. PUSH: local → remote (Machine A writes to B)
```

---

## 3. FO Corrections Required

These must be fixed in `sync-api-design.md` before Phase 3 implementation.

### 3.1 Critical: Entity Payload Column Names

| Entity | FO Says | Actual Column | Action |
|--------|---------|---------------|--------|
| PromptInstance | `name` | `label` | Fix payload spec |
| PromptInstance | `content` | `final_prompt` | Fix payload spec |
| PromptInstance | `field_values` (JSON) | Does not exist | Remove from spec |
| PromptTemplate | `content` (Quill Delta) | `template_body` | Fix payload spec |

### 3.2 Critical: TemplateField Structure

The FO assumes TemplateField has `id` (PK), `uuid`, `order`, `created_at`, `updated_at`, `deleted_at`.

**Reality:** Composite-key pivot table with only `template_id` + `field_id`. No PK, no timestamps, no `order`.

**Decision:** Treat as derived entity. Sync by FK pair, not UUID. Remove from UUID/deleted_at migration scope.

### 3.3 High: Missing Columns in FO Payloads

| Entity | Missing from FO | Status in Current Code |
|--------|----------------|----------------------|
| Project | `prompt_instance_copy_format` | Already in `EntityDefinitions` |
| Project | `label` | Already in `EntityDefinitions` |
| Project | `claude_options` (JSON) | **Not synced** — add to FO, mark machine-specific |
| Project | `claude_context` (Quill Delta) | **Not synced** — add to FO, mark machine-specific |
| ScratchPad | `response` (Quill Delta) | **Not synced** — BUG, add to both FO and `EntityDefinitions` |

### 3.4 High: Columns That Don't Exist

| Entity | FO Claims | Reality | Action |
|--------|-----------|---------|--------|
| Field | Has `order` column | No `order` column on `field` table | Remove from payload |
| Field | Has `value` column | Content stored in `content` column | Fix payload |
| ProjectLinkedProject | Has `label` column | No `label` column | Remove from payload |
| TemplateField | Has `order` column | Column does not exist | Remove from payload |

### 3.5 Medium: Non-Existent Field Type

FO references `file_list`. Actual types from `FieldConstants::TYPES`: `text, select, multi-select, code, select-invert, file, directory, string, number`. File-exclusion applies to `file` and `directory` types (`FieldConstants::PATH_FIELD_TYPES`).

### 3.6 Medium: Sync Order

Both the FO order (project_linked_project last) and the current code order (project_linked_project second) are correct for the single-user scenario. Both projects exist in the sync set after step 1. The FO order is marginally safer for future multi-user scenarios. No change required for Phase 1-2.

### 3.7 Low: `since` Parameter

Remove from MVP API spec. Document as post-MVP delta sync optimization.

---

## 4. Design Decisions

### D1: FK CASCADE Constraints — Leave As-Is

Soft-delete is an UPDATE, not a DELETE. CASCADE constraints never fire during normal CRUD. They remain useful for user account deletion (hard cascade through entire entity graph) and as a safety net against accidental physical DELETEs.

### D2: Default Query Scope — Yes, Apply via Model `find()` Override

**The evidence is decisive.** `Project.deleted_at` already exists and uses the explicit opt-in approach. Only 1 of 14 `Project::find()` call sites (`availableForLinking`) filters `deleted_at`. The other 13 leak soft-deleted projects to the UI. This proves opt-in fails in practice.

**Implementation:** Override `find()` on each model to return a pre-filtered query:

```php
// In each Model class that has deleted_at
public static function find(): ModelQuery
{
    return (new ModelQuery(static::class))->active();
}
```

```php
// In each Query class
public function active(): static
{
    return $this->andWhere([self::tableName() . '.deleted_at' => null]);
}

public function withDeleted(): static
{
    // Must remove the active() condition added by find()
    // Implementation requires prototype — see note below
    return $this;
}

public function onlyDeleted(): static
{
    return $this->andWhere(['IS NOT', self::tableName() . '.deleted_at', null]);
}
```

**Prototype required.** Yii2 doesn't provide a clean API to remove a specific `andWhere` condition. The `withDeleted()` implementation needs to either:
- Reset and rebuild the where clause
- Use a flag checked in `active()` to conditionally skip the filter
- Use Yii2's `on` condition mechanism instead of `where`

Phase 2a implementation must prototype and verify the mechanism before committing.

**Bypass cases** (require `withDeleted()`):
- `FieldOptionController` data migration command
- Soft-delete cascade logic (finding children of a deleted parent)
- Future trash/restore UI

**No bypass needed:**
- `RecordFetcher` — uses raw `Query` objects, not ActiveRecord model queries

**Double-filtering:** `ProjectLinkedProjectQuery::linkedProjectIdsFor()` manually filters `deleted_at = null`. With default scope, this becomes redundant but harmless. MySQL optimizes away duplicate conditions.

### D3: Soft-Delete Cascade — Yes, Application-Level Cascade

When soft-deleting a parent entity, cascade soft-delete to all children. Without cascade, entities with `user_id` (Field, ScratchPad) appear in global lists even after their parent Project is soft-deleted.

| When soft-deleting | Also soft-delete |
|--------------------|-----------------|
| Project | Context, Field (+ FieldOption), PromptTemplate (+ PromptInstance), ScratchPad, ProjectLinkedProject |
| Field | FieldOption |
| PromptTemplate | PromptInstance |
| Context | — (leaf) |
| PromptInstance | — (leaf) |
| ScratchPad | — (leaf) |
| FieldOption | — (leaf) |
| ProjectLinkedProject | — (leaf) |

### D4: Unique Constraints — Generated Column for Partial Index

MySQL doesn't support `WHERE` clauses on unique indexes. Use a generated column:

```sql
ALTER TABLE field ADD COLUMN active_unique_key TINYINT(1)
    GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED;

-- NULL values are excluded from unique index checks
CREATE UNIQUE INDEX idx_field_project_id_name_active
    ON field (project_id, name, active_unique_key);
```

Tables requiring this treatment:

| Table | Existing Index | New Index |
|-------|---------------|-----------|
| `field` | `idx_field_project_id_name_unique` on `(project_id, name)` | `(project_id, name, active_unique_key)` |
| `field` | `idx-field-project_label_user-unique` on `(project_id, label, user_id)` | `(project_id, label, user_id, active_unique_key)` |
| `project` | `idx-project-user_label-unique` on `(user_id, label)` | `(user_id, label, active_unique_key)` |

Model unique validators must also add `'filter' => ['deleted_at' => null]`.

### D5: Dual FK on prompt_instance.template_id — Resolved

No dual FK exists. Migration `m250610_000002` has a guard clause that skips if an FK already exists. Only one FK is active: `ON DELETE CASCADE`. Soft-delete cascade from PromptTemplate to PromptInstance must be handled in application code.

### D6: Restore UI — No (Sync-Only)

Soft-delete is for sync deletion propagation, not a user-facing trash feature. Data recoverable via SQL. Adding `onlyDeleted()` scope as foundation for future restore UI.

### D7: user_id Mapping — Document as Single-User Assumption

`SyncController` accepts `--user-id` (default: 1). `user_id` is in `columns` but NOT in `foreignKeys` — copied as-is. This works because both machines have `user_id=1`. Phase 3 should add `user_id` remapping for multi-user scenarios.

### D8: EXT Placeholders — All Types Use Numeric IDs

All placeholder types (GEN, PRJ, EXT) are stored as `TYPE:{{numericId}}` in `template_body`. `PromptTemplateService::convertPlaceholdersToIds()` converts display format (`EXT:{{label:name}}`) to IDs at save time. The sync remapper regex handles all three types correctly.

---

## 5. Placeholder Integrity Problem

### The Bug

`template_body` contains `PRJ:{{42}}` where `42` is a local auto-increment field ID. During sync, `EntitySyncer::mapForeignKeys()` remaps `template_field.field_id` from source ID to destination ID. But `template_body` is copied verbatim — the `42` inside the Quill Delta JSON is NOT remapped.

### Solution: Regex Remapping on Sync (Phase 2b)

```php
public function remapPlaceholderIds(string $templateBody, array $fieldIdMapping): string
{
    return preg_replace_callback(
        '/(GEN|PRJ|EXT):\{\{(\d+)\}\}/',
        function (array $matches) use ($fieldIdMapping) {
            $type = $matches[1];
            $sourceId = (int) $matches[2];
            $destId = $fieldIdMapping[$sourceId] ?? $sourceId;
            return "{$type}:{{{$destId}}}";
        },
        $templateBody
    );
}
```

Call in `insertRecord`/`updateRecord` when entity is `prompt_template`, using `$report->idMappings['field']`.

### Split-Op Risk: LOW

If a user partially formats a placeholder in the Quill editor, Quill splits it across multiple Delta ops. The regex won't match a split placeholder. However, the existing `convertPlaceholdersToIds()` has the same limitation — it also processes per-op with regex. A split placeholder is already broken in stored data. The sync remapper passes it through unchanged, which is correct behavior for already-broken data.

### Post-Sync Validation

After remapping, compare field IDs extracted from `template_body` against `template_field` rows. Log warnings for mismatches.

---

## 6. FK Cascade Map

```
user (root)
├─► project.user_id                         CASCADE
├─► field.user_id                           CASCADE
├─► scratch_pad.user_id                     CASCADE
└─► user_preference.user_id                 CASCADE

project
├─► context.project_id                      CASCADE
├─► field.project_id                        CASCADE
├─► prompt_template.project_id              CASCADE
├─► scratch_pad.project_id                  CASCADE
├─► project_linked_project.project_id       CASCADE
└─► project_linked_project.linked_project_id CASCADE

field
└─► field_option.field_id                   CASCADE

prompt_template
├─► template_field.template_id              CASCADE
└─► prompt_instance.template_id             CASCADE

template_field
└─► template_field.field_id                 CASCADE
```

**Implications for soft-delete:** With soft-delete (UPDATE not DELETE), none of these cascades fire during normal CRUD. They remain active for user account deletion and as a safety net. No FK changes needed.

**Atomic introduction required.** Soft-delete must be introduced for all entities simultaneously. If Project uses soft-delete but Field still uses hard-delete, deleting a Field would CASCADE-destroy FieldOptions and TemplateField rows that the soft-deleted Project's templates depend on.

---

## 7. Phase 1: Schema Foundation

**Goal:** Add UUIDs, fix missing columns, fix EntitySyncer state leakage. No behavior change to sync.

**Estimate:** 12-16 hours

### 7.1 Migration: Add UUID Columns

Add `uuid CHAR(36)` + unique index to 8 tables: `project, context, field, field_option, prompt_template, prompt_instance, scratch_pad, project_linked_project`.

NOT `template_field` — remains a composite-key pivot table.

Each migration step:
1. Add column as `CHAR(36) NULL`
2. Backfill existing rows with UUIDv4
3. Set column to `NOT NULL`
4. Add unique index

**Dependency:** `ramsey/uuid` is NOT in `composer.json`. Must be added.

### 7.2 Model Changes: UUID Generation

```php
trait UuidTrait
{
    public function beforeSave($insert): bool
    {
        if ($insert && empty($this->uuid)) {
            $this->uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        }
        return parent::beforeSave($insert);
    }
}
```

Apply to all 8 models. Chains with existing `TimestampTrait` via parent calls.

### 7.3 Fix EntityDefinitions Columns

| Entity | Add to columns |
|--------|---------------|
| `project` | `uuid` |
| `context` | `uuid` |
| `field` | `uuid` |
| `field_option` | `uuid` |
| `prompt_template` | `uuid` |
| `prompt_instance` | `uuid` |
| `scratch_pad` | `response`, `uuid` |
| `project_linked_project` | `uuid` |

`claude_options` and `claude_context` deferred — machine-specific paths need explicit user decision.

### 7.4 Fix EntitySyncer State Leakage

`SyncService::run()` creates one `EntitySyncer` and reuses it for pull and push. Internal state (`sourceKeyLookup`, `destKeyLookup`) from pull may pollute push.

**Fix:** Create a fresh `EntitySyncer` for each direction:

```php
public function run(int $userId, bool $dryRun = false): array
{
    $remote = $this->getRemoteConnection();
    $remoteDb = $remote->connect();

    try {
        $pullSyncer = new EntitySyncer();
        $pullReport = $this->sync($remoteDb, $this->localDb, $userId, $dryRun, $pullSyncer);

        $pushSyncer = new EntitySyncer();
        $pushReport = $this->sync($this->localDb, $remoteDb, $userId, $dryRun, $pushSyncer);

        return ['pull' => $pullReport, 'push' => $pushReport];
    } finally {
        $remote->disconnect();
    }
}
```

### 7.5 Tests

- UUID generation on save (each model)
- EntityDefinitions column completeness (compare with actual schema)
- Sync order FK integrity
- Fresh EntitySyncer per direction (no state leakage)

### 7.6 Verification

```bash
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii vendor/bin/codecept run unit
docker exec pma_yii yii sync/status
```

---

## 8. Phase 2a: Soft-Delete Foundation

**Goal:** Add `deleted_at` to all entities, change CRUD from hard-delete to soft-delete, add query scopes. No sync behavior change.

**Estimate:** 25-30 hours

**Prerequisite:** Phase 1 (UUIDs exist for future DeletionPropagator)

### 8.1 Migration: Add `deleted_at` Columns

Add `deleted_at DATETIME NULL` to 7 tables:
- `context`
- `field`
- `field_option`
- `prompt_template`
- `prompt_instance`
- `scratch_pad`
- `project_linked_project`

`project` already has `deleted_at`. `template_field` doesn't need it (rows are derived).

### 8.2 Migration: Generated Columns for Partial Unique Indexes

For tables with unique indexes that must exclude soft-deleted rows:

```sql
-- field
ALTER TABLE {{%field}} ADD COLUMN active_unique_key TINYINT(1)
    GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED;
DROP INDEX idx_field_project_id_name_unique ON {{%field}};
CREATE UNIQUE INDEX idx_field_project_id_name_active ON {{%field}} (project_id, name, active_unique_key);
DROP INDEX `idx-field-project_label_user-unique` ON {{%field}};
CREATE UNIQUE INDEX `idx_field_project_label_user_active` ON {{%field}} (project_id, label, user_id, active_unique_key);

-- project (already has deleted_at)
ALTER TABLE {{%project}} ADD COLUMN active_unique_key TINYINT(1)
    GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) STORED;
DROP INDEX `idx-project-user_label-unique` ON {{%project}};
CREATE UNIQUE INDEX idx_project_user_label_active ON {{%project}} (user_id, label, active_unique_key);
```

### 8.3 Model Unique Validator Updates

Add `'filter' => ['deleted_at' => null]` to all unique validation rules:

| Model | Validator / Rule | Change |
|-------|-----------------|--------|
| `Field` | `validateUniqueNameWithinProject()` | Add `->andWhere(['deleted_at' => null])` |
| `Field` | Unique rule on `[project_id, label, user_id]` | Add `'filter' => ['deleted_at' => null]` |
| `PromptTemplate` | Unique rule on `[name, project_id]` | Add `'filter' => ['deleted_at' => null]` |
| `Context` | Unique rule on `[project_id, name]` | Add `'filter' => ['deleted_at' => null]` |
| `Project` | Unique rule on `[user_id, label]` | Add `'filter' => ['deleted_at' => null]` |

### 8.4 Query Scopes: Default Filtering

Add `active()`, `withDeleted()`, `onlyDeleted()` to each Query class. Override `find()` on each model to apply `active()` by default.

**Query classes to modify:** `ProjectQuery, ContextQuery, FieldQuery, PromptTemplateQuery, PromptInstanceQuery, ScratchPadQuery, ProjectLinkedProjectQuery`

**Query class to create:** `FieldOptionQuery` (does not exist — `FieldOption` currently uses base `ActiveQuery`)

**Prototype first:** Verify the `withDeleted()` mechanism works in Yii2 before modifying all 8 query classes.

### 8.5 CRUD: Hard Delete → Soft Delete

Complete list of deletion paths that must change:

| Location | Current | Phase 2a Change |
|----------|---------|-----------------|
| `ProjectController::actionDelete()` | `$model->delete()` | Soft-delete via service with cascade |
| `ContextService::deleteContext()` | `$model->delete()` | `$model->deleted_at = now; $model->save()` |
| `FieldService::deleteField()` | `$field->delete()` | Soft-delete + cascade to FieldOptions |
| `ModelService::deleteModelSafely()` (PromptTemplate) | `$model->delete()` | Soft-delete + cascade to PromptInstances |
| `ModelService::deleteModelSafely()` (PromptInstance) | `$model->delete()` | `$model->deleted_at = now; $model->save()` |
| `ScratchPadController::actionDelete()` | `$model->delete()` | `$model->deleted_at = now; $model->save()` |
| `ProjectService::syncLinkedProjects()` | `$link->delete()` | **Keep as hard-delete** — link records are derived state |

### 8.6 Soft-Delete Cascade Logic

Project soft-delete requires a service method with transaction:

```php
public function softDeleteProject(Project $project): void
{
    $now = date('Y-m-d H:i:s');
    $transaction = Yii::$app->db->beginTransaction();
    try {
        $project->deleted_at = $now;
        $project->save(false);

        Context::updateAll(
            ['deleted_at' => $now],
            ['project_id' => $project->id, 'deleted_at' => null]
        );
        Field::updateAll(
            ['deleted_at' => $now],
            ['project_id' => $project->id, 'deleted_at' => null]
        );
        PromptTemplate::updateAll(
            ['deleted_at' => $now],
            ['project_id' => $project->id, 'deleted_at' => null]
        );
        ScratchPad::updateAll(
            ['deleted_at' => $now],
            ['project_id' => $project->id, 'deleted_at' => null]
        );

        // Grandchildren
        $fieldIds = (new Query())
            ->select('id')
            ->from('field')
            ->where(['project_id' => $project->id])
            ->column();
        if ($fieldIds) {
            FieldOption::updateAll(
                ['deleted_at' => $now],
                ['field_id' => $fieldIds, 'deleted_at' => null]
            );
        }

        $templateIds = (new Query())
            ->select('id')
            ->from('prompt_template')
            ->where(['project_id' => $project->id])
            ->column();
        if ($templateIds) {
            PromptInstance::updateAll(
                ['deleted_at' => $now],
                ['template_id' => $templateIds, 'deleted_at' => null]
            );
        }

        ProjectLinkedProject::updateAll(
            ['deleted_at' => $now],
            ['or', ['project_id' => $project->id], ['linked_project_id' => $project->id]]
        );

        $transaction->commit();
    } catch (\Throwable $e) {
        $transaction->rollBack();
        throw $e;
    }
}
```

Note: grandchild queries use raw `Query` to avoid the `active()` default scope.

### 8.7 Tests

- Default query scopes exclude soft-deleted records (each entity)
- `withDeleted()` returns all records including soft-deleted
- `onlyDeleted()` returns only soft-deleted records
- Soft-delete cascade: Project delete → all children soft-deleted
- Field delete → FieldOptions soft-deleted
- PromptTemplate delete → PromptInstances soft-deleted
- CRUD: soft-delete works for each controller/service
- Unique validators allow re-creating same-name entities after soft-delete
- Generated column indexes: unique constraint active for non-deleted, ignored for deleted
- Regression: all list/index views do not show soft-deleted records

### 8.8 Verification

```bash
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii vendor/bin/codecept run unit
# Manual: verify each entity list view, create/delete/recreate cycle
```

---

## 9. Phase 2b: Sync Enhancement

**Goal:** Sync deletions between machines, log conflicts, fix placeholder ID translation.

**Estimate:** 25-30 hours

**Prerequisite:** Phase 2a (soft-delete infrastructure exists)

### 9.1 Soft-Delete vs Natural Key Matching

With soft-delete, natural key uniqueness breaks. Scenario:
1. Machine A creates Field "Status" in Project "MyApp"
2. Field syncs to Machine B
3. Machine A soft-deletes Field "Status"
4. Machine A creates a new Field "Status" in the same project
5. Sync: Machine A has two records with identical natural key — one active, one deleted

`buildSemanticKey()` produces identical keys for both records. One overwrites the other in the lookup table.

**Solution:** Separate active-record sync from deletion propagation.

1. `EntitySyncer` continues to handle active records only. `RecordFetcher` adds `AND deleted_at IS NULL` to all entity queries.
2. New `DeletionPropagator` handles soft-delete propagation as a second pass, using UUID matching (available after Phase 1).

### 9.2 DeletionPropagator

```php
class DeletionPropagator
{
    /**
     * For each entity type, find records that are soft-deleted on source
     * but active on dest, matched by UUID.
     */
    public function propagate(
        Connection $sourceDb,
        Connection $destDb,
        int $userId,
        bool $dryRun
    ): DeletionReport {
        foreach (EntityDefinitions::getSyncOrder() as $entity) {
            $definition = EntityDefinitions::getAll()[$entity];
            if (!in_array('deleted_at', $definition['columns'], true))
                continue;

            // Get soft-deleted records from source (by UUID)
            $sourceDeleted = $this->getDeletedRecords($sourceDb, $entity, $userId);

            // Match against active records on dest (by UUID)
            foreach ($sourceDeleted as $record) {
                $destRecord = $this->findByUuid($destDb, $entity, $record['uuid']);
                if ($destRecord && $destRecord['deleted_at'] === null) {
                    // Source deleted, dest active — propagate if source deletion is newer
                    if (strtotime($record['deleted_at']) > strtotime($destRecord['updated_at'])) {
                        $this->softDelete($destDb, $entity, $destRecord['id'], $record['deleted_at']);
                    }
                }
            }
        }
    }
}
```

### 9.3 Update RecordFetcher

Two changes:
1. Add `AND deleted_at IS NULL` to all entity queries (active records only for `EntitySyncer`)
2. Add `deleted_at` to fetched columns for entities that have it (for `DeletionPropagator`)

### 9.4 Update EntityDefinitions Columns

Add `deleted_at` to the `columns` array for all entities that have it (all except `template_field`).

### 9.5 Placeholder ID Remapping

Add `remapPlaceholderIds()` to `EntitySyncer` (or a new `PlaceholderRemapper` class). Call in `insertRecord`/`updateRecord` when entity is `prompt_template`, using `$report->idMappings['field']`.

See [Section 5](#5-placeholder-integrity-problem) for implementation.

### 9.6 Migration: sync_conflict_log Table

```sql
CREATE TABLE {{%sync_conflict_log}} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_uuid CHAR(36) NOT NULL,
    direction ENUM('pull', 'push') NOT NULL,
    local_updated_at DATETIME NOT NULL,
    remote_updated_at DATETIME NOT NULL,
    resolution ENUM('local_wins', 'remote_wins') NOT NULL,
    local_snapshot JSON NULL,
    remote_snapshot JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_entity (entity_type, entity_uuid),
    INDEX idx_created (created_at)
);
```

### 9.7 ConflictResolver Enhancement

Extend `ConflictResolver` to return audit records when source overwrites destination:

```php
public function resolveWithAudit(
    array $sourceRecord,
    array $destRecord,
    string $entityType
): ConflictResult {
    $winner = $this->resolve($sourceRecord['updated_at'], $destRecord['updated_at']);
    // Return object with: winner, loser snapshot, timestamps
}
```

Write results to `sync_conflict_log` during sync.

### 9.8 SyncService Orchestration Update

```php
public function run(int $userId, bool $dryRun = false): array
{
    $remote = $this->getRemoteConnection();
    $remoteDb = $remote->connect();

    try {
        // Active record sync (existing flow, fresh syncer per direction)
        $pullSyncer = new EntitySyncer();
        $pullReport = $this->sync($remoteDb, $this->localDb, $userId, $dryRun, $pullSyncer);

        $pushSyncer = new EntitySyncer();
        $pushReport = $this->sync($this->localDb, $remoteDb, $userId, $dryRun, $pushSyncer);

        // Deletion propagation (new, Phase 2b)
        $propagator = new DeletionPropagator();
        $pullDeletions = $propagator->propagate($remoteDb, $this->localDb, $userId, $dryRun);
        $pushDeletions = $propagator->propagate($this->localDb, $remoteDb, $userId, $dryRun);

        return [
            'pull' => $pullReport,
            'push' => $pushReport,
            'pullDeletions' => $pullDeletions,
            'pushDeletions' => $pushDeletions,
        ];
    } finally {
        $remote->disconnect();
    }
}
```

### 9.9 Tests

- Deletion propagation: source soft-deleted → dest soft-deleted
- Deletion propagation: dest is newer → no propagation
- Deletion propagation: UUID matching works across machines
- Conflict log written on overwrite
- Placeholder ID remapping in template_body (all three types: GEN, PRJ, EXT)
- Post-sync validation: template_body field IDs match template_field rows
- RecordFetcher excludes soft-deleted from active sync
- RecordFetcher includes deleted_at in columns for deletion propagation

### 9.10 Verification

Same as Phase 2a, plus:
- Run sync between two databases with soft-deleted records
- Verify deletions propagate both directions
- Verify conflict log has entries after conflicting sync
- Verify template placeholders work after sync

---

## 10. Phase 3: HTTP API

**Goal:** Replace SSH/MySQL transport with HTTP API. Add peer management.

**Estimate:** 40-56 hours

**Prerequisite:** Phase 2b

### 10.1 Relationship to Existing Code

| Existing Class | Phase 3 Fate |
|----------------|-------------|
| `SyncService` | **Refactored** — orchestration stays, transport changes |
| `EntitySyncer` | **Kept** — core sync logic unchanged |
| `EntityDefinitions` | **Kept** — already updated in Phase 1 |
| `RecordFetcher` | **Kept** for local queries; remote queries become API calls |
| `ConflictResolver` | **Kept** — already enhanced in Phase 2b |
| `DeletionPropagator` | **Kept** — uses UUID matching, transport-agnostic |
| `RemoteConnection` | **Replaced** by `SyncApiClient` |
| `SyncReport` | **Kept** |
| `SyncController` (CLI) | **Kept** alongside new web controller |

### 10.2 New API Controller

`yii/controllers/api/SyncApiController.php`:
- `GET /api/sync/v1/handshake` — peer verification
- `GET /api/sync/v1/manifest` — all UUIDs + timestamps
- `GET /api/sync/v1/pull` — full entities by UUID
- `POST /api/sync/v1/push` — accept entities from remote

Authentication: Bearer token validated against `sync_peer.access_token_hash`.

### 10.3 New Tables

**sync_peer:**

```sql
CREATE TABLE {{%sync_peer}} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peer_id CHAR(36) NOT NULL,
    peer_name VARCHAR(255) NULL,
    peer_url VARCHAR(255) NOT NULL,
    access_token_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_handshake_at DATETIME NULL,
    api_version VARCHAR(10) DEFAULT '1.0',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX idx_sync_peer_id (peer_id)
);
```

**sync_log:**

```sql
CREATE TABLE {{%sync_log}} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peer_id CHAR(36) NOT NULL,
    sync_started_at DATETIME NOT NULL,
    sync_completed_at DATETIME NULL,
    entities_pushed INT DEFAULT 0,
    entities_pulled INT DEFAULT 0,
    conflicts_count INT DEFAULT 0,
    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_peer_sync (peer_id, sync_started_at)
);
```

### 10.4 New Service: SyncApiClient

Replaces `RemoteConnection`:

```php
class SyncApiClient
{
    public function __construct(
        private readonly string $peerUrl,
        private readonly string $accessToken,
        private readonly string $localPeerId
    ) {}

    public function handshake(): array { /* GET /handshake */ }
    public function manifest(): array { /* GET /manifest */ }
    public function pull(array $uuids): array { /* GET /pull */ }
    public function push(array $entities): array { /* POST /push */ }
}
```

**Dependency:** HTTP client (Guzzle or Yii2 built-in).

### 10.5 ManifestComparator

Takes local and remote manifests (UUID + updated_at + deleted_at per entity) and determines:
- Entities to pull (remote newer or remote-only)
- Entities to push (local newer or local-only)
- Deletions to propagate (soft-deleted on one side)

Replaces the current "fetch all records and compare in memory" approach.

### 10.6 user_id Remapping

Phase 3 must add `user_id` mapping. Options:
- Treat `user_id` as a FK in `EntityDefinitions` and use `idMappings`
- Accept a `targetUserId` parameter and overwrite before insert/update

### 10.7 Peer Management UI

Settings page additions:
- Generate peer token
- Add remote peer (URL + token)
- View peer list with status
- Revoke peer
- Manual sync trigger
- Sync log viewer

### 10.8 Backward Compatibility

SSH-based CLI sync (`yii sync/run`) remains available during transition. Deprecated once HTTP API is proven stable.

### 10.9 Tests

- ManifestComparator: pull/push/delete set identification
- SyncApiClient: mocked HTTP responses
- API controller: authentication (valid, invalid, revoked)
- API controller: correct payloads
- Integration: full sync cycle via API (two test databases)

---

## 11. What Does Not Change

Across all phases, these remain unchanged:

- User-facing CRUD flows (other than delete → soft-delete in Phase 2a)
- Quill Delta format and storage
- Placeholder system (`GEN/PRJ/EXT:{{id}}`) — IDs are remapped during sync, not changed in the application
- RBAC rules and owner checks
- View layer (no visual changes until Phase 3 UI)
- Frontend JavaScript
- `PromptGenerationService`, `PromptTemplateService`, `CopyFormatConverter`
- Identity module (User, auth)
- Search functionality

---

## 12. Risks and Mitigations

| Risk | Phase | Impact | Mitigation |
|------|-------|--------|------------|
| `withDeleted()` mechanism doesn't work cleanly in Yii2 | 2a | HIGH — blocks all soft-delete work | Prototype first; if Yii2 can't remove conditions, use flag-based approach |
| Soft-delete cascade misses children, orphaned records visible | 2a | HIGH — data leaks in UI | Transaction-wrapped cascade; test every parent-child relationship |
| Generated column approach unsupported on MySQL version | 2a | HIGH — blocks unique index migration | Verify MySQL 8.0+ on both machines before starting |
| UUID backfill on large tables locks rows | 1 | MEDIUM — brief downtime | Run backfill in batches; test on copy of production data |
| Placeholder remapping regex misses edge cases | 2b | MEDIUM — broken placeholders | Test with actual `template_body` data from production |
| SSH sync and HTTP sync diverge in behavior | 3 | MEDIUM — inconsistent results | Keep both running in parallel during Phase 3; compare outputs |
| `user_id` mismatch between machines | Existing | LOW (single-user) | Documented assumption; Phase 3 adds remapping |
| ConflictResolver equal-timestamp bias (source wins) | Existing | LOW | Documented; acceptable for single-user bidirectional sync |

---

## 13. Assumptions and Constraints

| # | Assumption | Impact If Wrong |
|---|-----------|-----------------|
| 1 | Single user per machine (`user_id=1` on both) | `user_id` copied verbatim; wrong user owns records on destination |
| 2 | Both machines have same PromptManager version | Schema mismatches cause sync failures |
| 3 | Both machines have MySQL 8.0+ | Generated columns for partial indexes not supported on older versions |
| 4 | `ramsey/uuid` can be added to `composer.json` | Blocks Phase 1 UUID generation |
| 5 | `claude_options` and `claude_context` are machine-specific and should NOT sync | Data loss if user expects these to sync |
| 6 | `ProjectLinkedProject` always links projects owned by the same user | Multi-user would break sync scoping |

### Effort Summary

| Phase | Scope | Estimate |
|-------|-------|----------|
| FO corrections | Document editing only | 2-3 hours |
| Phase 1 | Migrations + UUID trait + EntityDefinitions + state leakage fix + tests | 12-16 hours |
| Phase 2a | Soft-delete + query scopes + cascade + CRUD changes + tests | 25-30 hours |
| Phase 2b | DeletionPropagator + conflict log + placeholder fix + tests | 25-30 hours |
| Phase 3 | HTTP API + client + peer management + UI + tests | 40-56 hours |
| **Total** | | **104-135 hours** |

Phases 1, 2a, 2b can be done without Phase 3. Phase 3 is the FO's target architecture but is optional if SSH sync remains acceptable.

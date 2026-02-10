# Critical Review — Sync System Improvement (Final Specification)

**Reviewer:** PromptManager Analyst
**Document under review:** `.claude/design/fo/sync-improvement-plan.md` v1.0
**Date:** 2026-02-10

---

## Verdict: SOLID — with corrections needed

This is an unusually thorough design document. It correctly identifies real bugs, traces entity relationships, and proposes a phased evolution that doesn't break what works. The author clearly read the source code — most claims are verified against actual implementations.

That said, the review below identifies factual errors, ambiguities, missing edge cases, and scope concerns that must be resolved before implementation.

---

## 1. Factual Errors

### 1.1 D5 — FK on prompt_instance.template_id is RESTRICT, not CASCADE

The document says in D5: "Only one FK is active: `ON DELETE CASCADE`."

**Actual:** The initial migration (`m230101_000001_initial_migration.php:196-204`) sets `ON DELETE RESTRICT`. The later migration (`m250610_000002`) would set CASCADE but **skips** because it detects the existing FK. The active constraint is `ON DELETE RESTRICT`.

**Impact:** When Phase 2a converts PromptTemplate deletion to soft-delete, the RESTRICT constraint is actually beneficial — it prevents accidental hard-deletes of templates that have instances. But the document should state the correct constraint. The claim in Section 6 (FK Cascade Map) that `prompt_template → prompt_instance.template_id` is `CASCADE` is also wrong.

**Action:** Correct D5 and Section 6 to state `ON DELETE RESTRICT`. Note that this means the soft-delete cascade for PromptTemplate → PromptInstance in Phase 2a is *essential*, not just *preferred* — a hard delete would fail with an FK violation.

### 1.2 Codebase analysis says ScratchPad uses Unix timestamps

The `codebase_analysis.md` states ScratchPad has `created_at: int | Unix timestamp`. The design doc at Section 8.6 uses `date('Y-m-d H:i:s')` for soft-delete timestamps.

**Actual:** ScratchPad declares `@property string $created_at` and uses `TimestampTrait` which outputs `date('Y-m-d H:i:s')`. All models use datetime strings after the timestamp conversion migration (`m260116_000003_convert_timestamps_to_datetime`). The codebase analysis is stale.

**Impact:** The design's use of `date('Y-m-d H:i:s')` for `$now` in the cascade method is correct. No bug here — just noting the codebase_analysis.md should be updated.

### 1.3 Section 1 — "14 Project::find() call sites, only 1 filters deleted_at"

**Actual count:** 14 `Project::find()` calls exist in production code. However, **zero** of the 14 directly filter `deleted_at`. The `availableForLinking()` method does filter it, but it lives inside `Project::findAvailableForLinking()` which creates its own `Project::find()` — making it a 15th call, not one of the 14.

**Impact:** The argument for default scoping is actually *stronger* than stated: 0 of 14, not 1 of 14. The conclusion (D2) is correct; the evidence is understated.

---

## 2. Design Concerns

### 2.1 D2 — `withDeleted()` implementation is hand-waved

The document acknowledges this is a problem ("Prototype required") but doesn't commit to a solution. Three options are listed without evaluation. This is the critical path for Phase 2a — everything depends on it.

**Recommendation:** A flag-based approach is simplest and most reliable in Yii2:

```php
class ModelQuery extends ActiveQuery
{
    private bool $includeDeleted = false;

    public function active(): static
    {
        if (!$this->includeDeleted) {
            $this->andWhere([self::tableName() . '.deleted_at' => null]);
        }
        return $this;
    }

    public function withDeleted(): static
    {
        $this->includeDeleted = true;
        return $this;
    }
}
```

But this requires `withDeleted()` to be called **before** `active()` is applied (i.e., before `find()` constructs the query). Since `find()` calls `->active()` immediately, `withDeleted()` would need to come before `find()` — which is impossible with the standard pattern.

Alternative: override `find()` to *not* call `active()`, and instead apply the filter lazily in `createCommand()` or `prepare()`. This is the pattern used by Yii2 extensions like `yii2-soft-delete`.

**Bottom line:** Prototype this *first*, as the document suggests. But budget 4-8 hours for the prototype alone — it's non-trivial in Yii2.

### 2.2 Phase 2a — Soft-delete cascade queries use raw Query but grandchild field IDs may be stale

Section 8.6 uses raw `(new Query())->from('field')` for grandchild queries to avoid the `active()` scope. This is correct. But the field query at line 603 has `->where(['project_id' => $project->id])` — this fetches **all** field IDs for the project, including already-soft-deleted ones.

If `softDeleteProject()` is called twice (e.g., a re-delete after restore in a future phase), the `updateAll` for FieldOptions would also set `deleted_at` on options belonging to fields that were independently soft-deleted earlier (with a different `deleted_at` timestamp). This overwrites their original deletion time.

**Recommendation:** The condition `['deleted_at' => null]` on the field/template subqueries is already present for `updateAll` targets. Verify the same condition is applied to the `SELECT id` subqueries for grandchildren:

```php
$fieldIds = (new Query())
    ->select('id')
    ->from('field')
    ->where(['project_id' => $project->id, 'deleted_at' => null])  // <-- add this
    ->column();
```

Wait — re-reading the document, the `updateAll` call already has `'deleted_at' => null` as a condition, so it won't overwrite already-deleted options. The `SELECT id` without the filter is technically over-inclusive but harmless because the `updateAll` won't match the already-deleted children. **Not a bug, but misleading.** Adding the filter to the SELECT makes intent clearer and avoids an unnecessary IN clause with stale IDs.

### 2.3 Phase 2b — DeletionPropagator lacks cascade

`DeletionPropagator` (Section 9.2) propagates soft-deletes per-entity by UUID matching. But it doesn't cascade. If a Project is soft-deleted on Machine A, DeletionPropagator will soft-delete the Project on Machine B — but not its children (Contexts, Fields, Templates, etc.).

**Question:** Does the existing cascade logic from Phase 2a handle this? No — Phase 2a cascades on CRUD operations (when a user deletes via the UI). DeletionPropagator runs *after* the active-record sync, directly writing `deleted_at` to the database via raw queries. It bypasses the application layer entirely.

**Options:**
1. DeletionPropagator triggers the application-level cascade after propagating a parent deletion
2. DeletionPropagator propagates deletions for all entities independently (children are soft-deleted on source, so they'll be matched by UUID)
3. DeletionPropagator only handles parent entities, relying on cascade logic to handle children on the destination

Option 2 is correct *if* the source machine cascaded properly. Source Machine A soft-deleted the Project, which cascaded to all children. Those children are now soft-deleted on source. DeletionPropagator iterates all entities in sync order, so it will find and propagate each child's deletion independently.

**But:** DeletionPropagator compares `deleted_at` against `updated_at`. If a child entity was updated *after* the cascade (e.g., the user edited a field, then later deleted the parent project, and the cascade set `deleted_at` with a timestamp *older* than `updated_at`), the timestamp comparison may incorrectly skip propagation.

Wait — the cascade sets `deleted_at = $now` where `$now` is the current time. Since `$now > updated_at` for all children (the cascade runs after any edits), this is safe. Unless the user edits a child *after* the parent cascade... but that's impossible because the child is soft-deleted and hidden from the UI.

**Verdict:** Option 2 works. But the document should explicitly state this assumption: "DeletionPropagator propagates all entity soft-deletes independently; it relies on source-side cascade having already soft-deleted children."

### 2.4 Phase 1 — ramsey/uuid dependency

The document flags this as a prerequisite. Confirmed: `ramsey/uuid` is not in `composer.json`.

**Alternative:** PHP 8.2 doesn't have built-in UUID generation, but `Yii::$app->security->generateRandomString(36)` or formatting random bytes as UUID v4 is trivial without an external dependency:

```php
$uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    random_int(0, 0xffff), random_int(0, 0xffff),
    random_int(0, 0xffff),
    random_int(0, 0x0fff) | 0x4000,
    random_int(0, 0x3fff) | 0x8000,
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
);
```

**Recommendation:** If the only use is generating UUID v4 strings, consider whether adding a Composer dependency is justified. A 10-line helper may be simpler. Decision per workflow rules: "Coordinate before adding Composer dependencies."

### 2.5 Section 3.3 — claude_options/claude_context deferred indefinitely

The document notes these columns are "not synced" and defers the decision. Section 7.3 also defers. This is a reasonable deferral, but it should be an explicit Assumption:

**Missing assumption:** `claude_options` (JSON — Claude CLI permission mode, model, etc.) and `claude_context` (Quill Delta — project-level Claude instructions) contain machine-specific configuration. Syncing them would overwrite local Claude settings with remote ones.

**Counter-argument:** Some `claude_options` fields (model choice, permission mode) may be user preferences that *should* sync. The `root_directory` field on Project *does* sync despite being machine-specific.

**Recommendation:** Add to Assumptions table: "claude_options and claude_context are NOT synced. If user expectations change, this can be revisited without schema changes."

### 2.6 Phase 2a — FieldOptionController data migration needs `withDeleted()`

Section D2 lists `FieldOptionController` as a bypass case requiring `withDeleted()`. Verified: `FieldOptionController` (line 28) calls `FieldOption::find()->each()`. Since `FieldOption` will get a default `active()` scope in Phase 2a, this migration command would skip soft-deleted options.

**But:** The `FieldOptionController` is a one-time data migration command. If it has already run before Phase 2a is deployed, this is a non-issue. If it might run again, it needs `withDeleted()`.

**Recommendation:** Confirm whether this command is still needed. If it's a one-time migration that has already executed, it doesn't need modification.

---

## 3. Missing Edge Cases

### 3.1 Natural key collision for global fields

`Field` with `project_id = NULL` (global fields) uses natural key `['name', 'user_id']`. The sync semantic key for a field includes the parent project's semantic key (resolved via FK). But for global fields, `project_id` is NULL — there is no parent project.

**Question:** How does `buildSemanticKey()` handle a NULL FK? Looking at `EntitySyncer::buildSemanticKey()`, it resolves each FK column by looking up the parent's semantic key in the appropriate lookup table. If `project_id` is NULL, the lookup returns null, and the method returns null for the entire semantic key — meaning **global fields cannot be matched by semantic key**.

Verified: `buildSemanticKey` at the FK resolution step does `$report->getMappedId($parentEntity, $fkValue)`. If `$fkValue` is NULL, getMappedId returns null, and the semantic key building fails.

**Impact:** Global fields (`project_id = NULL`) are likely **not synced correctly** in the current implementation. They would fail semantic key building and be skipped or errored. This is a pre-existing bug not mentioned in the document.

**Recommendation:** Add to Known Bugs list. Fix in Phase 1: handle NULL FKs in `buildSemanticKey()` by using a sentinel value (e.g., `"__GLOBAL__"`) for the parent key component.

### 3.2 ScratchPad with project_id = NULL

Same issue as 3.1. ScratchPads can be global (`project_id = NULL`). The sync natural key is `['name', 'user_id']` with FK `project_id → project`. Same NULL FK problem.

### 3.3 Unique constraints don't match natural keys

The sync system uses natural keys for matching:
- `field`: natural key `['name', 'user_id']` — but the DB unique index is on `['project_id', 'name']`
- `project`: natural key `['name', 'user_id']` — but no unique index on `['name', 'user_id']`; only `['user_id', 'label']` is unique

This means:
- Two projects with the same `name` but different `label` values are valid in the DB but would collide in sync's semantic key matching
- Two fields with the same `name` in different projects would have different semantic keys (because the parent project key differs), so this works
- But two global fields (`project_id = NULL`) with the same name are prevented by the DB index but would fail semantic key building anyway (see 3.1)

**Impact:** If a user has two projects with the same name, sync may incorrectly match them. This is a pre-existing fragility.

**Recommendation:** Document this as a known limitation. Phase 1 UUID-based matching would resolve it, but only once `EntitySyncer` switches from natural keys to UUID matching.

### 3.4 template_field and project_linked_project have empty natural keys

Both pivot entities have `naturalKeys: []`. Their semantic key is composed entirely of FK-resolved parent keys. This works correctly as long as both parents are synced first. But with empty natural keys, two template_field records linking the same template and field are indistinguishable — which is correct since the FK pair is the identity.

**No action needed** — just noting this is handled correctly.

### 3.5 Phase 2b — DeletionPropagator iterates ALL soft-deleted records every sync

Section 9.2 fetches all soft-deleted records from source and matches them against active records on dest. On a system with many soft-deleted records accumulated over time, this becomes increasingly expensive.

**Recommendation:** Add to Phase 3 scope: use `deleted_at > last_sync_at` to limit the set. For Phase 2b with SSH sync, this is acceptable — the dataset is small.

---

## 4. Ambiguities

### 4.1 Phase 1 UUID backfill — which UUID version?

Section 7.1 says "backfill existing rows with UUIDv4" and the trait uses `Uuid::uuid4()`. This is fine, but the document doesn't specify whether UUID format matters for future HTTP API. UUID v4 is random; UUID v7 is time-sortable and has better index performance.

**Recommendation:** UUID v4 is fine for this use case (identity matching, not sorting). Confirm and move on.

### 4.2 Phase 2a — Does ProjectLinkedProject get `deleted_at`?

Section 8.1 lists `project_linked_project` in the 7 tables getting `deleted_at`. But Section 8.5 says `ProjectService::syncLinkedProjects()` should "keep as hard-delete" for link records.

**Contradiction?** Adding `deleted_at` to the table but keeping hard-delete in CRUD means the column exists but is never written to by the application. It would only be written by the soft-delete cascade (Section 8.6) when a parent project is soft-deleted.

**Question:** Is this intentional? If so, what happens when the project is restored (future phase)? The cascaded `deleted_at` on links would need to be cleared, but since `syncLinkedProjects()` hard-deletes and re-creates links, a restore would need to re-sync links explicitly.

**Recommendation:** Clarify the dual behavior. Either:
- Keep hard-delete for `syncLinkedProjects()` AND exclude `project_linked_project` from soft-delete migration (simpler)
- Add `deleted_at` but acknowledge that link records have hybrid behavior (soft-delete only via cascade, hard-delete via direct CRUD)

The second option is what the document specifies but it should be called out explicitly.

### 4.3 Phase 2a — What about `template_field` pivot records when a Field is soft-deleted?

When a Field is soft-deleted, the document specifies cascading to FieldOptions. But `template_field` records linking templates to that field are not addressed.

**Impact:** After soft-deleting a field, `template_field` rows still reference it. The template's `template_body` still contains the placeholder `TYPE:{{field_id}}`. The PromptGenerationService would try to resolve the placeholder, look up the field, and — with default `active()` scope — fail to find it.

**Options:**
1. Leave `template_field` rows intact. The template still "knows" about the field. If the field is restored, everything works again.
2. Soft-delete `template_field` rows too. But `template_field` doesn't have `deleted_at` by design.
3. Hard-delete `template_field` rows when a field is soft-deleted. But this loses the association.

**Recommendation:** Option 1 is safest. But `PromptGenerationService` needs to handle missing fields gracefully (skip the placeholder or show a "[deleted field]" marker). This is a behavioral change that should be called out.

### 4.4 Phase 2b — Placeholder remapper regex vs. actual storage format

The document's remapper uses pattern `(GEN|PRJ|EXT):\{\{(\d+)\}\}` — matching `TYPE:{{42}}`.

Verified: `convertPlaceholdersToIds()` outputs `TYPE:{{id}}` (double braces). The `PlaceholderProcessor` pattern matches `TYPE:{{id}}`. The remapper pattern is consistent.

**No issue** — just confirming.

---

## 5. Scope Concerns

### 5.1 Phase 2a is very large (25-30 hours)

Phase 2a touches:
- 7 tables (migration)
- 3 tables (generated column migration)
- 5+ model validators
- 7 query classes (+ create 1 new)
- 6+ deletion paths in controllers/services
- 1 cascade service method
- Default query scope mechanism (unproven in Yii2)
- Extensive tests

**Recommendation:** Split Phase 2a into:
- **2a-i:** Migration + generated columns + query scopes + prototype `withDeleted()` (schema and read-side changes, no behavioral change)
- **2a-ii:** CRUD conversion to soft-delete + cascade logic + validator updates (write-side changes)

This allows deploying the schema first and verifying it before changing application behavior.

### 5.2 Phase 3 scope is enormous (40-56 hours)

Phase 3 includes a new API controller, authentication, peer management UI, ManifestComparator, SyncApiClient, user_id remapping, and backward compatibility. This is essentially a new subsystem.

**Recommendation:** The document already notes Phase 3 is optional. Agree — it should be a separate specification.

---

## 6. What the document gets right

Credit where due:

- **Bug identification** — All 5 known bugs are real and verified against source code
- **Entity tracing** — The FK cascade map (Section 6) is accurate (except the RESTRICT/CASCADE error on prompt_instance)
- **Phase isolation** — Each phase is independently deployable. The evolution strategy is sound.
- **Generated columns for partial indexes** — Correct approach for MySQL 8.0 soft-delete unique constraints
- **Atomic soft-delete introduction** — Section 6's warning about introducing soft-delete for all entities simultaneously is critical and correct
- **RecordFetcher bypass** — Correctly identifies that `RecordFetcher` uses raw `Query` objects and won't be affected by model-level default scopes
- **Existing code preservation** — Phase 3 correctly identifies which classes to keep, refactor, or replace

---

## 7. Action Items

| # | Priority | Action |
|---|----------|--------|
| 1 | **Must fix** | Correct D5 and Section 6: prompt_instance FK is RESTRICT, not CASCADE |
| 2 | **Must fix** | Document global field/scratchpad NULL FK semantic key bug (Section 3.1/3.2 above) |
| 3 | **Must fix** | Clarify DeletionPropagator cascade assumption (Section 2.3 above) |
| 4 | **Should fix** | Clarify `project_linked_project` hybrid delete behavior (Section 4.2) |
| 5 | **Should fix** | Address `template_field` behavior when a Field is soft-deleted (Section 4.3) |
| 6 | **Should fix** | Evaluate `ramsey/uuid` vs. inline UUID generation (Section 2.4) |
| 7 | **Should fix** | Add `claude_options`/`claude_context` to Assumptions table explicitly (Section 2.5) |
| 8 | **Consider** | Split Phase 2a into schema-only and behavior-change sub-phases (Section 5.1) |
| 9 | **Consider** | Add `deleted_at > last_sync_at` optimization to Phase 3 scope (Section 3.5) |
| 10 | **Cosmetic** | Correct "1 of 14" to "0 of 14" for Project::find() filtering (Section 1.3) |

# Refactor: INT Timestamps to DATETIME

## GOAL

Convert all Unix timestamp (INT) columns to DATETIME across all tables for better readability in queries and database dumps.

## UNIT_OF_WORK

One phase from the todos.md file (e.g., "Phase 1: Shared Infrastructure", "Phase 3: Models").

## AGENT_MEMORY_LOCATION

`.claude/refactor/int-to-datetime/`

---

**Before you start**:

- Read the **context file** at `[AGENT_MEMORY_LOCATION]/context.md` to understand the goal and approach.
- Read the **todos file** at `[AGENT_MEMORY_LOCATION]/todos.md` to see which tables need processing.
- Read the **insights file** at `[AGENT_MEMORY_LOCATION]/insights.md` for accumulated learnings.

**As you work**:

- After processing each table, update the **insights file** with any discoveries or issues.
- Check off each table in the **todos file** as you complete it.
- **SAFETY RULE:** Do not treat files inside `[AGENT_MEMORY_LOCATION]` as database input. They are tracking files only.
- **CRITICAL:** Update the todos file **before** your memory gets compacted.
- After any memory compaction or pause, **read the context and todos files first** to re-orient.

**For each phase**:

### Phase 1: TimestampTrait
- Change `$timestampOverride` type from `?int` to `?string`
- Change `time()` to `date('Y-m-d H:i:s')`

### Phase 2: Migration
- Create ONE migration for all 25 columns across 11 tables
- Use add-temp/convert/drop/rename pattern per column
- `FROM_UNIXTIME()` in safeUp, `UNIX_TIMESTAMP()` in safeDown

### Phase 3: Models
For each model file:
- Change `@property int` to `@property string` for `*_at` columns
- Remove timestamp columns from `integer` validation rule

### Phase 4: Services
- `UserService::generateAccessToken()` — use `date('Y-m-d H:i:s', strtotime("+{$days} days"))`
- `UserService::isAccessTokenExpired()` — use `strtotime($expires) < time()`
- `User::findIdentityByAccessToken()` — use `date('Y-m-d H:i:s')` in query

### Phase 5: Fixtures
- Change `time()` to `date('Y-m-d H:i:s')` in all fixture data files

### Phase 6: Tests
- Update timestamp assignments from `time() + N` to `date('Y-m-d H:i:s', strtotime('+N seconds'))`
- Update assertions that compare timestamps

### Phase 7: CLI
- `UserController` — remove `date()` wrapper since value is already datetime string

### Phase 8: Verification
- Run migrations on both schemas
- Run full test suite
- Verify column types

**Termination Condition**:

Continue until all 8 phases have been completed and checked off in the todos list.

---

## Verification Commands

```bash
# Run migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Run all tests
docker exec pma_yii vendor/bin/codecept run unit

# Check column types
docker exec pma_mysql mysql -u root -proot -e "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='yii' AND COLUMN_NAME LIKE '%_at' ORDER BY TABLE_NAME" 2>/dev/null
```

## Tables (25 columns across 11 tables)

| Table | Columns |
|-------|---------|
| user | `created_at`, `updated_at`, `deleted_at`, `access_token_expires_at` |
| project | `created_at`, `updated_at`, `deleted_at` |
| context | `created_at`, `updated_at` |
| field | `created_at`, `updated_at` |
| field_option | `created_at`, `updated_at` |
| prompt_template | `created_at`, `updated_at` |
| template_field | `created_at`, `updated_at` |
| prompt_instance | `created_at`, `updated_at` |
| user_preference | `created_at`, `updated_at` |
| project_linked_project | `created_at`, `updated_at` |
| scratch_pad | `created_at`, `updated_at` |

# Insights: Project Load

## Findings
- TimestampTrait uses `date('Y-m-d H:i:s')` format (DATETIME strings, not integers)
- Project model afterDelete() calls claudeWorkspaceService->deleteWorkspace()
- Project model afterSave() calls claudeWorkspaceService->syncConfig()
- Both must be avoided — using raw SQL inserts/deletes
- PromptTemplateService::convertPlaceholdersToIds() uses pattern: parse delta JSON, iterate ops, preg_replace_callback on `/(GEN|PRJ|EXT):\{\{(.+?)}}/`
- ClaudeCliService uses proc_open() with descriptor spec [stdin pipe, stdout pipe, stderr pipe]
- EntityDefinitions shows sync order and FK relationships — useful reference
- template_field has no auto-increment PK, no timestamps — just template_id + field_id + sort_order
- project.label has unique constraint on (user_id, label) — need to handle conflicts
- DB connection available via Yii::$app->db

## Decisions
- Service directory: `yii/services/projectload/` (lowercase, consistent with `yii/services/sync/`)
- Using proc_open() for mysql import (consistent with ClaudeCliService pattern)
- Column exclude list as class constant in EntityConfig

## Verification Results

**Linter (session 1):** php-cs-fixer fixed 2 files:
- `ProjectLoadService.php` — multi-argument method calls formatting (each arg on own line)
- `ProjectLoadController.php` — FQCN `\app\services\projectload\LoadReport` in method body replaced with `use` import

**Tests (session 1):** 51 tests, 152 assertions, 0 failures, 2 skipped

**Tests + Linter (session 3 — final):** 877 tests, 2181 assertions, 0 errors, 0 failures, 21 skipped
- 7 test files in `tests/unit/services/projectload/`
- 21 skips: mysql/mysqldump CLI not available outside Docker (expected)
- Full suite passes including all other project tests
- Linter: 0 issues found in 255 files

### Bugs Found and Fixed (session 3)

1. **PDO positional parameter binding** in `EntityLoader::fetchFromTemp()` and `countInTemp()`:
   - Used `?` placeholders with 0-based array_values — PDO requires 1-based indexing
   - Fix: switched to named parameters (`:p0`, `:p1`, etc.)
   - Same fix applied to `ProjectLoadService::loadGlobalFields()`

2. **Fixture/DDL transaction conflict** in EntityLoaderTest, ProjectLoadServiceTest, PlaceholderRemapperTest:
   - DDL statements (CREATE DATABASE, CREATE TABLE) implicitly commit MySQL transactions
   - Codeception wraps each test in a transaction for cleanup (`cleanup: true`)
   - DDL in `_before()` broke the transaction, causing fixture data from previous test to persist
   - Result: `Duplicate entry '100' for key 'user.PRIMARY'` on fixture reload
   - Fix: removed `_fixtures()` from all 3 test classes; use `ensureUserExists()` / `ensureGlobalFieldExists()` instead
   - Tests dynamically look up IDs rather than hardcoding fixture IDs

## Open Issues
- None blocking. The 21 skipped tests require `mysql`/`mysqldump` CLI binary (Docker environment only, per spec §5.1)

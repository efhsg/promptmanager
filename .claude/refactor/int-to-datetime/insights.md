# Insights

## Discovered During Analysis

- **11 tables** with INT timestamp columns
- **25 total columns** to convert
- `deleted_at` only exists on `user` and `project` tables
- `access_token_expires_at` is a special case (already has custom handling in UserService)
- `template_field` table was simplified in a previous migration and no longer has timestamps, so skipped.
- `auth_assignment` and `auth_rule` fixtures exist but correspond to RBAC tables (not migrated), so their timestamps were NOT updated.
- `fields.php`, `field_options.php`, `project_linked_projects.php` fixtures used static integers, converted to `date(...)` calls.
- `UserServiceTest.php` in `modules/identity` was checking `assertIsInt` for `deleted_at`, updated to `assertIsString`.
- `UserControllerTest.php` was calculating expected expiry using `time()`, updated to use `date()` and `strtotime`.

## Potential Issues

- `TimestampBehavior` default uses `time()` — needs explicit `Expression('NOW()')` config
- Some models may have custom timestamp handling beyond the behavior
- Tests that mock timestamps need updating

## Decisions Made

- **Phase 1**: Changed `TimestampTrait::$timestampOverride` to string.
- **Phase 3**: 
  - `User` model uses `TimestampBehavior`. Updated it to use `value => fn() => date('Y-m-d H:i:s')` instead of `Expression('NOW()')`. This maintains consistency with other models using `TimestampTrait` (returning string vs object) and avoids breaking code that expects `created_at` to be a string immediately after save.
  - Skipped `TemplateField` as it has no timestamps.
- **Phase 4**:
  - `UserService::softDelete` was using `time()`. Updated to `date('Y-m-d H:i:s')`.
  - `UserService::isAccessTokenExpired` updated to use `strtotime`.
- **Phase 5**:
  - Excluded `auth_assignment` and `auth_rule` from fixture updates.
- **Phase 6**:
  - Updated tests to expect strings instead of integers for timestamps.
  - Updated `createSchema` in tests to use `string`/`text` columns instead of `integer` for timestamps in SQLite memory DBs.

## Lessons Learned

- Always check if models use Trait or Behavior. `User` was different from the rest.
- Fixtures can be tricky if they involve system tables.
- Tests often recreate schema in memory (SQLite), which needs manual update to match migration changes.
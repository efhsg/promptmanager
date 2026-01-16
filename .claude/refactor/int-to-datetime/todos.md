# INT to DATETIME Refactoring - Progress

## Phase 1: Shared Infrastructure

- [x] **TimestampTrait** (`yii/models/traits/TimestampTrait.php`)
  - Change `$timestampOverride` from `?int` to `?string`
  - Change `time()` to `date('Y-m-d H:i:s')`

## Phase 2: Database Migration

Create ONE migration for all tables (simpler rollback):

- [x] **Migration** `m260116_000003_convert_timestamps_to_datetime.php`
  - Convert all 25 columns across 11 tables
  - Use `FROM_UNIXTIME()` in safeUp, `UNIX_TIMESTAMP()` in safeDown

## Phase 3: Models (11 files)

- [x] `yii/modules/identity/models/User.php` — docblock + rules
- [x] `yii/models/Project.php` — docblock + rules
- [x] `yii/models/Context.php` — docblock + rules
- [x] `yii/models/Field.php` — docblock + rules
- [x] `yii/models/FieldOption.php` — docblock + rules
- [x] `yii/models/PromptTemplate.php` — docblock + rules
- [x] `yii/models/TemplateField.php` — docblock + rules (if exists)
- [x] `yii/models/PromptInstance.php` — docblock + rules
- [x] `yii/models/UserPreference.php` — docblock + rules
- [x] `yii/models/ProjectLinkedProject.php` — docblock + rules
- [x] `yii/models/ScratchPad.php` — docblock + rules

## Phase 4: Services with Custom Timestamp Logic

- [x] `yii/modules/identity/services/UserService.php`
  - `generateAccessToken()` — change `time() + (days * 86400)` to `date()`
  - `isAccessTokenExpired()` — change `< time()` to `strtotime() < time()`
- [x] `yii/modules/identity/models/User.php`
  - `findIdentityByAccessToken()` — change `time()` to `date('Y-m-d H:i:s')`

## Phase 5: Fixtures (10 files)

- [x] `yii/tests/fixtures/data/user.php`
- [x] `yii/tests/fixtures/data/projects.php`
- [x] `yii/tests/fixtures/data/contexts.php`
- [x] `yii/tests/fixtures/data/fields.php`
- [x] `yii/tests/fixtures/data/field_options.php`
- [x] `yii/tests/fixtures/data/prompt_template.php`
- [x] `yii/tests/fixtures/data/prompt_instance.php`
- [x] `yii/tests/fixtures/data/user_preference.php`
- [x] `yii/tests/fixtures/data/project_linked_projects.php`
- [x] `yii/services/UserDataSeeder.php` (if uses timestamps)

## Phase 6: Tests with Timestamp Assertions

- [x] `yii/tests/unit/modules/identity/models/UserTest.php`
- [x] `yii/tests/unit/modules/identity/UserServiceTest.php`
- [x] `yii/modules/identity/tests/unit/services/UserServiceTest.php`
- [x] `yii/tests/unit/commands/UserControllerTest.php`
- [x] `yii/modules/identity/tests/unit/commands/UserControllerTest.php`
- [x] `yii/tests/unit/models/ProjectTest.php`
- [x] `yii/tests/unit/models/ContextTest.php`
- [x] `yii/tests/unit/models/ScratchPadTest.php`
- [x] `yii/tests/unit/services/FieldServiceTest.php`
- [x] `yii/tests/unit/controllers/api/ScratchPadControllerTest.php`

## Phase 7: CLI Commands

- [x] `yii/commands/UserController.php` — remove `date()` wrapper for display

## Phase 8: Verification

- [x] Run migrations on `yii` schema
- [x] Run migrations on `yii_test` schema
- [x] Run full test suite
- [x] Verify column types in database

## Summary

| Category | Count | Status |
|----------|-------|--------|
| Shared trait | 1 | Done |
| Migration | 1 | Done |
| Models | 11 | Done |
| Services | 2 | Done |
| Fixtures | 10 | Done |
| Tests | 10 | Done |
| CLI | 1 | Done |

**Total files to modify:** ~36

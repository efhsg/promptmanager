# Implementation Steps

- [x] Create `yii/services/ClaudeRunCleanupService.php`
- [x] Modify `yii/controllers/ClaudeController.php` — add DI, actions, VerbFilter, access
- [x] Modify `yii/views/claude/runs.php` — add delete column + cleanup button
- [x] Create `yii/views/claude/cleanup-confirm.php`
- [x] Create `yii/tests/unit/services/ClaudeRunCleanupServiceTest.php`
- [x] Create `yii/tests/fixtures/ClaudeRunFixture.php` + data file
- [x] Fix `yii/tests/unit/controllers/ClaudeControllerTest.php` — add ClaudeRunCleanupService to constructor calls
- [x] Run linter + tests — 0 errors, 0 failures, 1067 tests pass

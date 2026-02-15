# Async Inference — Insights & Decisions

## Decisions
- Follow NoteType enum pattern for ClaudeRunStatus
- Follow Note model / NoteQuery pattern for ClaudeRun / ClaudeRunQuery
- Follow NoteOwnerRule pattern for ClaudeRunOwnerRule
- Console ClaudeRunController separate from existing ClaudeController (commands)
- Jobs directory `yii/jobs/` is new — first job in the project
- Storage directory `yii/storage/claude-runs/` for NDJSON stream files

## Findings
- No existing `yii/jobs/` directory — created it
- yii2-queue not yet in composer.json — installed v2.3.8
- Queue table migration is handled by yii2-queue's own migration (5 migrations)
- Console config already has migrationPath set to null (namespace migrations)
- Docker compose currently has no queue worker service — added `pma_queue`

## Pitfalls
- **yii2-queue migration path**: Running `./yii migrate --migrationPath=@yii/queue/db/migrations` failed with `Class "M161119140200Queue" does not exist`. Must use `--migrationNamespaces="app\migrations,yii\queue\db\migrations"` instead.
- **Octal syntax**: php-cs-fixer flagged `mkdir($dir, 0775)` — PHP 8.2 requires `0o775` octal prefix.
- **Codeception verify**: `verify()->contains()` does not exist for scalar/array assertions. Use `verify(in_array($val, $arr, true))->true()` instead.
- **Test FK constraints**: UserFixture only provides user IDs 100 and 1. Tests must use these IDs (not arbitrary values like 200).
- **connection_aborted() in CLI**: Must guard with `PHP_SAPI !== 'cli'` — function is undefined in CLI context.

## Final Results

**Linter**: 0 issues (1 fix applied: octal syntax in RunClaudeJob.php)

**Tests**: 1035 tests, 2510 assertions, 0 errors, 0 failures, 21 skipped (pre-existing)

**Migrations**: 6 migrations applied to both `yii` and `yii_test` schemas (5 yii2-queue + 1 claude_run)

## Open Issues
- None — all implementation steps completed successfully

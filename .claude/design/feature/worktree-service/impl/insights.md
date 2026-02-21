# Implementation Insights

## Decisions

- **Migration naming**: Used `m260222_000000` to match today's date. No `unsigned()` on PK since existing project table uses regular int.
- **Query class join**: `ProjectWorktreeQuery::forUser()` uses `innerJoinWith('project p', false)` to filter by user via project relation. The `false` param prevents eager loading the project relation unnecessarily.
- **Table name in model**: Used `{{%project_worktree}}` with table prefix syntax for consistency with migration.
- **Service error sanitization**: All git error output is logged via `Yii::warning()` but never returned to client. User-facing messages are generic.
- **Controller `beforeAction()` for JSON format**: All 6 actions are AJAX/JSON, so format is set once in `beforeAction()` instead of per-action.
- **Controller `findWorktree()`**: Uses `ProjectWorktreeQuery::forUser()` with join to verify ownership via project relation.
- **Git worktree prune before recreate**: Added `git worktree prune` before `recreate` to clean stale references.
- **Sync merge abort**: If merge fails during sync, the service aborts the merge to restore a clean state.

## Findings

- Pre-existing test failure: `AiRunControllerTest::testCleanupStaleIgnoresNonRunningRuns` has FK constraint error — unrelated.
- Project fixture data has no `root_directory` set — service tests use temp directories with `initGitRepo()` helper.
- `git worktree add -b` fails if branch already exists — `recreate` uses `git worktree add` without `-b`.
- `PathService::translatePath()` returns the original path if no mapping matches.

## Pitfalls

- Unique constraint validator needs `targetAttribute` as array `['project_id', 'path_suffix']` to validate composite uniqueness.
- `exec()` output must be captured with `2>&1` to include stderr in the output array.
- PHP 8.1 octal syntax: linter auto-fixes `0777` → `0o777`.

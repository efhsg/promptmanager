# Insights

## Decisions
- No PHP files were changed (all paths in Yii are relative via `__DIR__`/`dirname()`), so linter step was N/A.
- Documentation files in `.claude/design/` were intentionally NOT updated — they document historical situation per spec.
- Heredoc in Dockerfile changed from `<<'EOF'` (quoted) to `<<EOF` (unquoted) to enable `${APP_ROOT}` variable expansion during build.
- Added `mkdir -p ${APP_ROOT}` before `chown` because `/var/www/worktree/main` doesn't exist in the base PHP image.
- Used `${WORKDIR}` instead of `${APP_ROOT}/yii` for the PHPStorm symlink section since WORKDIR is already correctly defined.

## Test Results
- All 1228 unit tests pass (2931 assertions, 21 pre-existing skips, 0 failures)

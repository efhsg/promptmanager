---
allowed-tools: Bash, Read, Glob, Grep
description: Validate changes, run linter and tests, prepare commit
---

# Finalize Changes

## Steps

### 1. Identify changed files

```bash
git status --porcelain
```

### 2. Check rules compliance

Read `.claude/rules/coding-standards.md`, then verify each changed PHP file. Report violations.

### 3. Run linter

```bash
docker exec pma_yii vendor/bin/php-cs-fixer fix
```

### 4. Run relevant tests

Map changed files to test files:
- `models/Foo.php` → `tests/unit/models/FooTest.php`
- `services/FooService.php` → `tests/unit/services/FooServiceTest.php`

```bash
docker exec pma_yii vendor/bin/codecept run unit <test-path>
```

If tests fail, stop and report.

### 5. Prepare commit

```bash
git add -A
git reset HEAD -- .claude/screenshots/ 2>/dev/null || true
git status
git diff --staged
```

Note: Screenshots in `.claude/screenshots/` are always unstaged - they are for reference only.

Suggest commit message per `.claude/rules/commits.md`. Ask user for confirmation.

If linter passed and tests passed (no failures), end your response with:
"Ready to commit? Run `/commit-push` to commit and push to origin."

## Task

$ARGUMENTS

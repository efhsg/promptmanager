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

### 2. Check RULES.md compliance

Read `RULES.md`, then read each changed PHP file and verify compliance. Report violations.

### 3. Run linter

```bash
docker exec pma_yii vendor/bin/php-cs-fixer fix
```

### 4. Run relevant tests

Map changed files to test files:
- `models/Foo.php` → `tests/unit/models/FooTest.php`
- `services/FooService.php` → `tests/unit/services/FooServiceTest.php`
- `components/Foo.php` → `tests/unit/components/FooTest.php`

```bash
docker exec pma_yii vendor/bin/codecept run unit <test-path>
```

If tests fail, stop and report.

### 5. Prepare commit

```bash
git add -A
git status
git diff --staged
```

Suggest commit message (ADD:/CHG:/FIX:/DOC:, ~70 chars). Ask user for confirmation.

## Task

$ARGUMENTS

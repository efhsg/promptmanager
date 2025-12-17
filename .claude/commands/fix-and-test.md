---
allowed-tools: Bash(docker exec:*)
description: Run linter and tests for the current changes
---

# Fix and Test

Run linter and tests for the current changes.

## Steps

1. Run relevant unit tests (specify path or run all):
```bash
docker exec pma_yii vendor/bin/codecept run unit
```

2. Run specific test file:
```bash
docker exec pma_yii vendor/bin/codecept run unit services/MyServiceTest
```

3. Run specific test method:
```bash
docker exec pma_yii vendor/bin/codecept run unit services/MyServiceTest:testMethod
```

4. If tests fail, fix issues and re-run

## Task

$ARGUMENTS

If no specific path given, run full test suite.

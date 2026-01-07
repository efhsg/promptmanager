---
allowed-tools: Read, Edit, Write, Bash, Glob
description: Create unit tests for all staged PHP classes that lack tests
---

# Create Tests for Staged Classes

Create unit tests for all staged PHP classes that don't have tests yet.

Follow the skill contract in `skills/test.md` for test patterns.

## Process

1. Get staged files: `git diff --cached --name-only --diff-filter=A`
2. Filter for: `yii/services/`, `yii/models/`, `yii/common/enums/`, `yii/widgets/`
3. Exclude: tests/, interfaces, abstract classes
4. Check for existing tests
5. Generate missing tests using `skills/test.md` patterns

## Output

```
Created tests:
- yii/tests/unit/services/FooTest.php

Skipped (test exists):
- yii/services/Bar.php

Skipped (interface/abstract):
- yii/services/BazInterface.php
```

## Task

Analyze staged files and create missing tests.

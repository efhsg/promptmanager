---
allowed-tools: Read, Edit, Write, Bash, Glob
description: Create unit tests for all staged PHP classes that lack tests
---

# Create Tests for Staged Classes

Create unit tests for all staged PHP classes that don't have tests yet.

## Process

1. **Get staged files**
   ```bash
   git diff --cached --name-only --diff-filter=A
   ```
   Filter for newly added PHP files in relevant directories.

2. **Filter relevant classes**
   Only include files from:
   - `yii/services/` (not tests)
   - `yii/models/` (not tests)
   - `yii/common/enums/`
   - `yii/widgets/`

   Exclude:
   - Files already in `tests/` directories
   - Interfaces (`*Interface.php`)
   - Abstract classes (check file content for `abstract class`)

3. **Check for existing tests**
   For each class, check if a corresponding test file exists:
   - `yii/services/Foo.php` → `yii/tests/unit/services/FooTest.php`
   - `yii/services/bar/Baz.php` → `yii/tests/unit/services/bar/BazTest.php`
   - `yii/models/Foo.php` → `yii/tests/unit/models/FooTest.php`

4. **Generate tests**
   For each class without a test, create a test following the patterns in `/new-test`:

## Test Patterns

Reference: @.claude/commands/new-test.md

## Output

Report which tests were created:

```
Created tests:
- yii/tests/unit/services/copyformat/DeltaParserTest.php
- yii/tests/unit/services/copyformat/MarkdownWriterTest.php

Skipped (test exists):
- yii/services/CopyFormatConverter.php

Skipped (interface/abstract):
- yii/services/copyformat/FormatWriterInterface.php
- yii/services/copyformat/AbstractFormatWriter.php
```

## Task

Analyze staged files and create missing tests.

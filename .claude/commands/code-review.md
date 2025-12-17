---
allowed-tools: Bash(git diff:*), Bash(git log:*), Read, Grep
description: Perform a comprehensive code review of recent changes
---

## Context

- Current git status: !`git status`
- Recent changes: !`git diff HEAD~1`
- Recent commits: !`git log --oneline -5`
- Current branch: !`git branch --show-current`

## Your task

Perform a comprehensive code review focusing on:

1. **RULES.md Compliance**:
   - No `declare(strict_types=1)`
   - Full type hints on params/returns/properties
   - `use` imports, no inline FQCNs
   - No typed public properties for AR DB columns
   - Docblocks only for `@throws` or Yii2 magic

2. **Code Quality**: Readability, maintainability, SOLID/DRY applied appropriately

3. **Security**: SQL injection, XSS, unauthorized access vulnerabilities

4. **Performance**: N+1 queries, missing indexes, inefficient loops

5. **Testing**: Verify test coverage for new/changed behavior

Provide specific, actionable feedback with file:line references where appropriate.

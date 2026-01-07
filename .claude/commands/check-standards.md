---
allowed-tools: Bash(git diff:*), Bash(git status:*), Read, Grep
description: Check if staged changes follow project code standards
---

## Staged files

!`git diff --cached --name-only`

## Task

Read each staged PHP file and check against `.claude/rules/coding-standards.md`:

| Check | Rule |
|-------|------|
| No `declare(strict_types=1)` | Forbidden in this project |
| `use` imports | No inline FQCNs |
| Type hints | Parameters and return types fully typed |
| Docblocks | Only `@throws` or Yii2 magic |
| ActiveRecord | No typed public properties for DB columns |
| PSR-12 | Code style compliance |
| DI | Prefer DI in services |

## Output

Per file: Filename + Issues found (or "OK")

Summary: total issues, ready to commit?

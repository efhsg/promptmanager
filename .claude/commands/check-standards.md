---
allowed-tools: Bash(git diff:*), Bash(git status:*), Read, Grep
description: Check if staged changes follow project code standards
---

## Staged files

!`git diff --cached --name-only`

## Task

Read each staged PHP file and check against RULES.md standards:

| Check | Rule |
|-------|------|
| No `declare(strict_types=1)` | Forbidden in this project |
| `use` imports | No inline FQCNs (like `\App\Models\User`) |
| Type hints | Parameters and return types fully typed |
| Docblocks | Only `@throws` or Yii2 magic, no explanatory comments |
| ActiveRecord | No typed public properties for DB columns |
| PSR-12 | Code style compliance |
| DI | Prefer DI in services over `Yii::$app` access |

## Output

Per file a brief assessment:
- Filename
- Issues found (or "OK")

End with a summary: total number of issues and whether code is ready to commit.

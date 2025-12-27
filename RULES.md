# Project Rules

**Canonical rules have moved to `docs/rules/`**

## Rules Files

| File | Contents |
|------|----------|
| `docs/rules/coding-standards.md` | PHP/JS coding standards, non-negotiables |
| `docs/rules/architecture.md` | Folder structure, controller patterns |
| `docs/rules/testing.md` | Codeception requirements, test naming |
| `docs/rules/commits.md` | Commit message format (ADD:/CHG:/FIX:/DOC:) |
| `docs/rules/workflow.md` | Migrations, transactions, error handling |

## Instruction Precedence

If instructions conflict, follow this order:
1. `docs/rules/` (canonical rules)
2. `CLAUDE.md` / `AGENTS.md` / `GEMINI.md` (entry files)
3. `.claude/codebase_analysis.md` (architecture reference)

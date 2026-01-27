# AGENTS.md — AI Agent Configuration

Instructions for AI coding agents (Copilot, Cursor, Codeium, etc.) working with this codebase.

## Single Source of Truth

All configuration, rules, and patterns are defined in `CLAUDE.md`. Read and follow that file completely.

**Key references:**
- `CLAUDE.md` — Main configuration, role, domain essentials, behavioral guidelines
- `.claude/config/project.md` — Commands, file structure, test paths, domain concepts
- `.claude/rules/` — Coding standards, architecture, security, testing, commits, workflow
- `.claude/skills/` — Skill contracts for common tasks
- `.claude/codebase_analysis.md` — Architecture documentation

## Quick Reference

```bash
# Tests
docker exec pma_yii vendor/bin/codecept run unit

# Migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\\\migrations --interactive=0

# Linter
./linter.sh fix
./linter-staged.sh fix
```

## Before Any Code Change

1. Read `CLAUDE.md`
2. Load relevant rules from `.claude/rules/`
3. Check `.claude/skills/index.md` for applicable skills

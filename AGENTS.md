# AGENTS.md — OpenAI Codex Configuration

This file configures **OpenAI Codex** for the PromptManager repository.

## Single Source of Truth

All configuration, rules, and patterns are defined in `CLAUDE.md`. Read and follow that file completely.

**Key references:**
- `CLAUDE.md` — Main configuration, role, domain essentials, commands
- `.claude/rules/` — Coding standards, architecture, security, testing, commits, workflow
- `.claude/skills/` — Skill contracts for common tasks
- `.claude/codebase_analysis.md` — Architecture documentation

## Quick Reference

```bash
# Tests
docker exec pma_yii vendor/bin/codecept run unit

# Migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Code style
docker exec pma_yii vendor/bin/php-cs-fixer fix
```

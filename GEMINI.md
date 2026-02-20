# GEMINI.md — Google Gemini Configuration

This file configures **Google Gemini** for the PromptManager repository.

## Single Source of Truth

All configuration, rules, and patterns are defined in `CLAUDE.md`. Read and follow that file completely.

**Key references:**
- `CLAUDE.md` — Main configuration, role, domain essentials, behavioral guidelines
- `.claude/config/project.md` — Commands, file structure, test paths, domain concepts
- `.claude/rules/` — Coding standards, architecture, security, testing, commits, workflow
- `.claude/rules/skill-routing.md` — Auto-load skills by file pattern or topic
- `.claude/skills/` — Skill contracts for common tasks
- `.claude/codebase_analysis.md` — Architecture documentation

## Quick Reference

See `.claude/config/project.md` for the complete command reference. These commands assume execution from the **host system** (outside Docker):

```bash
# Tests
docker exec pma_yii vendor/bin/codecept run unit

# Migrations (both schemas required)
docker exec pma_yii ./yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii ./yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Linter
docker exec pma_yii vendor/bin/php-cs-fixer fix models/ --config=../.php-cs-fixer.dist.php --using-cache=no
```

> **Note:** AI tools running **inside** the `pma_yii` container use commands directly (without `docker exec`). See `.claude/config/project.md` for in-container commands.

## Before Any Code Change

1. Read `CLAUDE.md`
2. Load relevant rules from `.claude/rules/`
3. Check `.claude/skills/index.md` for applicable skills

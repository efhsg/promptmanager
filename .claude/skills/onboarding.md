# Onboarding Skill

Quick start guide for new sessions or developers.

## Project Overview

**PromptManager** is a Yii2 application for managing AI prompt templates with rich text editing.

**Tech Stack:**
- PHP 8.2+ / Yii2 framework
- Quill editor for rich text (Delta JSON format)
- Codeception for testing
- Docker (container: `pma_yii`)

**Domain Entities:**
```
Project → { Context, Field, PromptTemplate, Note } → PromptInstance
```

**Placeholder Syntax:**
- `GEN:{{name}}` — Global placeholder
- `PRJ:{{name}}` — Project placeholder
- `EXT:{{label:name}}` — Linked entity placeholder

## Key Files to Read

| Purpose | File |
|---------|------|
| Main instructions | `CLAUDE.md` |
| Commands & paths | `.claude/config/project.md` |
| Coding standards | `.claude/rules/coding-standards.md` |
| Architecture | `.claude/rules/architecture.md` |
| Available skills | `.claude/skills/index.md` |

## Quick Commands

Alle commands vanuit `/var/www/worktree/main/yii`:

```bash
# Run tests
vendor/bin/codecept run unit

# Run linter
vendor/bin/php-cs-fixer fix models/ --config=../.php-cs-fixer.dist.php

# Run migrations
./yii migrate --migrationNamespaces=app\\migrations --interactive=0
```

## Available Slash Commands

| Command | Purpose |
|---------|---------|
| `/review-changes` | Code review of changes |
| `/refactor` | Refactor code without behavior change |
| `/finalize-changes` | Lint, test, prepare commit |
| `/commit-push` | Commit and push |
| `/onboarding` | Show this quick start guide |
| `/new-branch` | Create feature/fix branch |

See `.claude/skills/index.md` for full command list.

## Next Steps

1. Check current git status: `git status`
2. Read relevant skill for your task from `.claude/skills/index.md`
3. Follow rules in `.claude/rules/` while coding

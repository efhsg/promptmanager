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
Project → { Context, Field, PromptTemplate, ScratchPad } → PromptInstance
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

```bash
# Run tests
docker exec pma_yii vendor/bin/codecept run unit

# Run linter
./linter.sh fix

# Run migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
```

## Available Slash Commands

| Command | Purpose |
|---------|---------|
| `/new-model` | Create ActiveRecord model + query class |
| `/new-service` | Create service class |
| `/new-controller-action` | Add controller action |
| `/new-test` | Create unit test |
| `/new-migration` | Create database migration |
| `/review-changes` | Code review of changes |
| `/refactor` | Refactor code without behavior change |
| `/finalize-changes` | Lint, test, prepare commit |
| `/onboarding` | Show this quick start guide |
| `/cp` | Commit and push |

Run `/help` for full command list.

## Next Steps

1. Check current git status: `git status`
2. Read relevant skill for your task from `.claude/skills/index.md`
3. Follow rules in `.claude/rules/` while coding

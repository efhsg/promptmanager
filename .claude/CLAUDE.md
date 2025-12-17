# CLAUDE.md

**PromptManager** - PHP 8.2 / Yii2 web application for organizing LLM prompts into projects, contexts, fields, and templates.

Read `RULES.md` first; it is the canonical reference for project conventions. This file is the operational checklist for Claude Code.

For detailed architecture, domain models, services, and code patterns, see `.claude/codebase_analysis.md`.

## Stack

- **Backend**: PHP 8.2, Yii2, MySQL
- **Frontend**: Bootstrap, Quill editor (built via `npm/`)
- **Infrastructure**: Docker (pma_yii, pma_mysql, pma_npm containers)

## Agent workflow

- If requirements are ambiguous, ask targeted questions before coding
- Implement end-to-end (code + targeted unit test when behavior changes)
- Make the smallest change that solves the task (avoid broad refactors unless requested)
- Prefer targeted unit tests over the full suite

## Commands

```bash
# Tests
docker exec pma_yii vendor/bin/codecept run unit
docker exec pma_yii vendor/bin/codecept run unit services/MyServiceTest:testMethod

# Migrations
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Frontend build
docker compose run --entrypoint bash pma_npm -c "npm run build-and-minify"
```

## Key paths

- Controllers/Services: `yii/controllers/`, `yii/services/`
- Models: `yii/models/`
- Views: `yii/views/`
- Migrations: `yii/migrations/`
- Tests: `yii/tests/`
- Identity module: `yii/modules/identity/`

## Definition of done

- Change is minimal and scoped to the request
- Change follows `RULES.md`
- Targeted unit test added/updated when behavior changes

## Response format

- Link to touched files by path and briefly describe what changed
- List the exact test commands you ran (or why you didn't)

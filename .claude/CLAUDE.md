# CLAUDE.md

Operational checklist for Claude Code. Read `RULES.md` first (canonical rules). See `.claude/codebase_analysis.md` for architecture details.

## Agent workflow

- If requirements are ambiguous, ask targeted questions before coding
- Implement end-to-end (code + targeted unit test when behavior changes)
- Make the smallest change that solves the task
- Prefer targeted unit tests over the full suite

## Commands

```bash
# Docker
docker compose up -d                    # Start containers
docker exec -it pma_yii bash            # Shell access

# Tests
docker exec pma_yii vendor/bin/codecept run unit
docker exec pma_yii vendor/bin/codecept run unit services/MyServiceTest:testMethod

# Migrations (run on both schemas)
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# Frontend build (after Quill/JS changes)
docker compose run --entrypoint bash pma_npm -c "npm run build-and-minify"

# Code style
docker exec pma_yii vendor/bin/php-cs-fixer fix
```

## Commit guidelines

- `ADD:` new features
- `CHG:` refactors/changes
- `FIX:` bug patches
- `DOC:` documentation only
- Keep messages concise (~70 characters)

## Definition of done

- Change is minimal and scoped to the request
- Change follows `RULES.md`
- Targeted unit test added/updated when behavior changes
- Migrations run on both `yii` and `yii_test` schemas

## Response format

- Link to touched files by path and briefly describe what changed
- List the exact test commands you ran (or why you didn't)

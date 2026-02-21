# Context — Phase 1: Worktree-ready fundament

## Goal

Replace all 20 hardcoded `/var/www/html` references across 8 production files with `${APP_ROOT}` (default `/var/www/worktree/main`), introducing a parent directory level for future worktree siblings. With only the main branch, behavior is 100% identical.

## Scope

### In
- 8 production files: `.env.example`, `Dockerfile`, `docker-compose.yml`, `nginx.conf.template`, `codex-config.toml`, `linter.sh`, `linter-staged.sh`, `launch.json`
- 7 documentation files: `CLAUDE.md`, `project.md`, `testing.md`, `onboarding.md`, `refactor.md`, `analyze-codebase.md`, `finalize-changes.md`
- APP_ROOT validation in linter scripts (security guardrails)

### Out
- No PHP/Yii code changes
- No worktree-specific logic (phase 2+)
- No parent mount (phase 2)
- No `.claude/design/` file changes

## Key References
- Spec: `.claude/design/feature/worktree-testing/phase-1/spec.md`
- Plan: `.claude/design/feature/worktree-testing/phase-1/plan.md`
- Reviews: `.claude/design/feature/worktree-testing/phase-1/reviews.md`

## Definition of Done
1. All 20 hardcoded `/var/www/html` replaced in 8 production files
2. APP_ROOT env var with fallback `/var/www/worktree/main` everywhere
3. Linter scripts validate APP_ROOT with allowlist regex and exit code 2 on failure
4. 7 documentation files updated
5. `grep -r '/var/www/html'` returns 0 matches (excluding `.claude/design/`)
6. No PHP/Yii code changes

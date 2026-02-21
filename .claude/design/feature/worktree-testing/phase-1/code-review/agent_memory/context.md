# Code Review Context

## Change
Refactor van alle hardcoded `/var/www/html` paden naar een geparametriseerde `${APP_ROOT}` variabele met default `/var/www/worktree/main`. Phase 1 van worktree-testing feature: pad-refactor.

## Scope
- `docker-compose.yml` — Volume mounts, working_dir, PATH, build args geparametriseerd
- `docker/yii/Dockerfile` — ARG APP_ROOT, mkdir, chown, error_log, WORKDIR geparametriseerd
- `docker/nginx.conf.template` — root directive via envsubst
- `.env.example` — Nieuwe APP_ROOT variabele
- `linter.sh` — APP_ROOT fallback + validatie + geparametriseerd pad
- `linter-staged.sh` — APP_ROOT fallback + validatie + geparametriseerde paden
- `.vscode/launch.json` — Xdebug pathMapping bijgewerkt
- `docker/yii/codex-config.toml` — Project-pad bijgewerkt
- `CLAUDE.md` + `.claude/` docs — Padreferenties bijgewerkt

## Type
Infrastructure/DevOps refactor (backend, geen frontend, geen PHP applicatiecode)

## Reviewvolgorde
1. Reviewer
2. Architect
3. Security
4. Developer
5. Tester

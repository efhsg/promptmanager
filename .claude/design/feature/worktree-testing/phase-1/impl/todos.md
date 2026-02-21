# Implementation Todos

## Steps
- [x] `.env.example` — add `APP_ROOT=/var/www/worktree/main`
- [x] `docker/yii/Dockerfile` — ARG + 5 hardcoded paths
- [x] `docker-compose.yml` — volumes, working_dir, PATH, build args, nginx env+envsubst (3 services)
- [x] `docker/nginx.conf.template` — root directive
- [x] `docker/yii/codex-config.toml` — trust path
- [x] `linter.sh` — config path + APP_ROOT validation
- [x] `linter-staged.sh` — sed prefix + config path + APP_ROOT validation
- [x] `.vscode/launch.json` — pathMapping
- [x] Documentation (7 files) — bulk `/var/www/html` → `/var/www/worktree/main` replace
- [x] Grep verification — 0 matches for `/var/www/html` in target files
- [x] Run linter — no PHP files changed, N/A
- [x] Run unit tests — 1228 tests, 2931 assertions, 0 failures

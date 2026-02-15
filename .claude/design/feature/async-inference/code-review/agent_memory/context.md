# Code Review Context

## Change
Implementatie van de "Async Claude Inference" feature: Claude CLI inference draait als background job (via yii2-queue DB driver) onafhankelijk van de browser-sessie. Bevat een nieuw domeinmodel (ClaudeRun), queue worker, file-based SSE stream relay, runs overzicht pagina, en migratie van directe streaming naar async flow.

## Scope
- docker-compose.yml — Nieuw pma_queue service (3 replicas)
- docker/yii/Dockerfile — PHP-FPM pool tuning + Claude Code installatie
- yii/composer.json + composer.lock — yiisoft/yii2-queue dependency
- yii/config/main.php — Queue component configuratie
- yii/config/rbac.php — claudeRun entity permissions
- yii/common/enums/ClaudeRunStatus.php — Nieuw: status enum
- yii/models/ClaudeRun.php — Nieuw: ActiveRecord model
- yii/models/query/ClaudeRunQuery.php — Nieuw: Query class
- yii/models/ClaudeRunSearch.php — Nieuw: Search model
- yii/jobs/RunClaudeJob.php — Nieuw: Background job
- yii/services/ClaudeStreamRelayService.php — Nieuw: Stream relay service
- yii/services/ClaudeCliService.php — CLI guard, conditionele PID store
- yii/controllers/ClaudeController.php — 7 nieuwe actions + async flow
- yii/handlers/ClaudeQuickHandler.php — Nieuwe use cases
- yii/rbac/ClaudeRunOwnerRule.php — Nieuw: RBAC rule
- yii/commands/ClaudeRunController.php — Nieuw: Console commands
- yii/migrations/m260215_000001_create_claude_run_table.php — Nieuw
- yii/migrations/m260215_000002_add_session_summary_to_claude_run.php — Nieuw
- yii/views/claude/index.php — Async endpoint URLs, replay variabelen
- yii/views/claude/runs.php — Nieuw: GridView runs overzicht
- yii/views/layouts/main.php — Nav link wijziging
- yii/views/layouts/_bottom-nav.php — Claude link naar /claude/runs
- yii/tests/unit/controllers/ClaudeControllerTest.php — Nieuwe tests
- Diverse nieuwe test-bestanden

## Type
Nieuwe feature (full-stack: backend + frontend + infra)

## Reviewvolgorde
1. Reviewer
2. Architect
3. Security
4. Front-end Developer
5. Developer
6. Tester

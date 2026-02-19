# Code Review Context

## Change
Multi-provider AI-ondersteuning (Claude + Codex) met provider-selectie UX, command substitution voor providers zonder native slash commands, en een sessie-overzichtspagina ("Dialogs"). De UX-wijzigingen omvatten locked-state visuele feedback, badge-groepering, en een fresh-load auto-expand voor settings.

## Scope
- `docker/yii/codex-config.toml` — Container-specifieke Codex config: sandbox uit, projectdirectory's trusted
- `yii/config/main.php` — DI-registratie ClaudeCliProvider, CodexCliProvider, AiProviderRegistry
- `yii/config/params.php` — Codex model-lijst (GPT 5.x reeks) toegevoegd aan params
- `yii/config/rbac.php` — `aiCommands` en `ai` action-permissions toegevoegd voor project/note
- `yii/controllers/AiChatController.php` — Provider-resolutie uit URL/session/default; per-provider configschema; cross-provider session guard
- `yii/controllers/ProjectController.php` — Multi-provider project form; `actionAiCommands` accepteert `?string $provider`
- `yii/jobs/RunAiJob.php` — Pre-executie slash command substitutie voor providers die dit niet native ondersteunen
- `yii/models/AiRunSearch.php` — Nieuw search model voor AI runs: filtering op status, tekst, project, all-projects
- `yii/services/EntityPermissionService.php` — Nieuwe model-based actions
- `yii/services/ai/AiConfigProviderInterface.php` — Nieuwe methode `supportsSlashCommands(): bool`
- `yii/services/ai/PromptCommandSubstituter.php` — NIEUW: Vervangt `/commandname` tokens in prompts
- `yii/services/ai/providers/ClaudeCliProvider.php` — `supportsSlashCommands()` → true; uitgebreid configschema
- `yii/services/ai/providers/CodexCliProvider.php` — Volledige Codex CLI provider
- `yii/services/projectload/EntityLoader.php` — Minimale wijziging
- `yii/tests/unit/jobs/RunAiJobTest.php` — Tests voor command substitutie, session management
- `yii/tests/unit/models/AiRunSearchTest.php` — 8 tests voor AiRunSearch
- `yii/tests/unit/services/ai/providers/CodexCliProviderTest.php` — Volledige test coverage CodexCliProvider
- `yii/tests/unit/services/ai/PromptCommandSubstituterTest.php` — NIEUW: 15 tests
- `yii/views/ai-chat/index.php` — Provider-selectie UX: locked state, badge groepering
- `yii/views/ai-chat/runs.php` — Sessie-overzicht met provider badges
- `yii/views/layouts/_bottom-nav.php` — "AI Chat" hernoemd naar "Dialogs"
- `yii/views/layouts/main.php` — "Dialogs" nav item
- `yii/views/note/index.php` — Minimale wijziging
- `yii/views/project/_form.php` — Multi-provider tabs in project settings form
- `yii/views/prompt-instance/_form.php` — Minimale wijziging
- `yii/web/css/ai-chat.css` — Locked badges, settings divider, pulse animatie
- `yii/web/css/mobile.css` — Mobiele responsive aanpassingen
- `yii/web/css/site.css` — Globale site styles
- `yii/web/index.php` — Minimale wijziging

## Type
Full-stack feature (nieuwe provider-integratie + UX + backend + tests)

## Reviewvolgorde
1. Reviewer
2. Architect
3. Security
4. Front-end Developer
5. Developer
6. Tester

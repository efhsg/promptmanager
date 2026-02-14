# Context — Async Claude Inference

## Doel
Maak Claude CLI inference onafhankelijk van de browser-sessie. Inference loopt door op de server, zelfs als de browser sluit. Lopende en voltooide runs zijn per gebruiker opvraagbaar.

## Scope
- Nieuwe `claude_run` tabel + model + migration
- Background worker met `yii2-queue` (DB driver)
- File-based stream relay met SSE endpoint
- Start/cancel/status/history endpoints
- Frontend: twee-staps flow, reconnect, runs overzicht
- Verwijdering van directe stream actions (na migratieperiode)

## User Story
De base specs staan in `.claude/design/feature/async-inference/spec.md` en moeten verfijnd worden op basis van de huidige codebase.

## Key References
- Bestaande ClaudeController: `yii/controllers/ClaudeController.php`
- Bestaande ClaudeCliService: `yii/services/ClaudeCliService.php`
- Frontend JS: `npm/src/js/editor-init.js` (ClaudeChat)
- Codebase analysis: `.claude/codebase_analysis.md`

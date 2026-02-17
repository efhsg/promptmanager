# Review Insights

## Beslissingen
- 2026-02-17: Reviewvolgorde bevestigd: Reviewer, Architect, Security, Front-end Developer, Developer, Tester

## Bevindingen

### Reviewer
- `claude_permission_mode` kolom bestond al niet meer (eerder samengevoegd in `claude_options` via migratie m260128_000002) — geen extra migratie nodig
- Deprecated `ProjectController::actionClaude()` redirect is bewust behouden voor backward-compat, dus rbac mapping `'claude' => 'viewProject'` is correct
- `instanceof ClaudeCliProvider` koppeling in controller voor `getGitBranch()` — doorgeschoven naar Architect review
- Duplicatie NDJSON parsing in RunAiJob vs ClaudeCliProvider::parseStreamResult() — doorgeschoven naar Developer review

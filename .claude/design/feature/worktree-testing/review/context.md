# Context

## Doel
Verbeter de specificatie `phases.md` (worktree-ready applicatie) tot minimaal 8/10 via multi-role review.

## Scope
De specificatie beschrijft een gefaseerd ontwerp om de PromptManager applicatie worktree-ready te maken:
- **Fase 1**: Fundament — Docker refactor, hardcoded paden parametrisch maken
- **Fase 2**: Bugfix worktrees — alleen source code gescheiden
- **Fase 3**: Small feature/refactor — vendor/.env gescheiden, DB gedeeld
- **Fase 4**: Big feature/spike — volledige isolatie

## User story
Als ontwikkelaar wil ik meerdere git worktrees tegelijk kunnen draaien en testen binnen de Docker-omgeving, zodat ik bugfixes, features en spikes geïsoleerd kan ontwikkelen zonder de main branch te verstoren.

## Status na 3 reviews
- Architect (8/10): Componentarchitectuur, migratie, env vars, foutafhandeling toegevoegd
- Security (8/10): Cookie-validatie, schema naming, DROP safeguards, port binding toegevoegd
- UX/UI (8/10): Worktree Selector wireframe, UI states, interactieflow toegevoegd
- Resterende reviews: Front-end Developer, Developer, Tester

## Ondersteunende documenten
- `problem.md` — probleemanalyse (5 blokkers + 18 hardcoded paden)
- `routing-and-sessions.md` — cookie-routing vs poort-routing
- `isolation-profiles.md` — isolatiedimensies per purpose
- `analysis-symlinks-and-nginx.md` — technische analyse vendor/env/nginx

# Context — Worktree Service

## Doel
Multi-role spec review van de Worktree Management feature voor PromptManager.

## Scope
Een generieke `WorktreeService` voor het beheren van meerdere git worktrees per project. Ondersteunt parallel werken aan features, agent workspaces en community skills — elk in een geïsoleerde worktree.

## User Story
Als gebruiker wil ik per project meerdere git worktrees kunnen aanmaken en beheren vanuit PromptManager, zodat ik (of meerdere agents) geïsoleerd kan werken aan features of andere taken — zonder elkaars werk of de main branch te verstoren.

## Specificatie
`.claude/design/feature/worktree-service/spec.md` — uitgebreide spec met FR-1 t/m FR-8, wireframes, technische details, test scenarios.

## Status
Fase 2 actief — 4 van 6 reviews afgerond. Volgende: Developer, Tester.

## Voltooide reviews
- Architect: 8/10 — fix recreate git cmd, view partial integratie
- Security: 9/10 — geen aanpassingen nodig
- UX/UI Designer: 8/10 — fix remove bevestiging naar Bootstrap modal
- Front-end Developer: 8/10 — fix modal HTML locatie, build tooling, asset registratie
- Developer: 8/10 — beforeAction() JSON format
- Tester: 8/10 — 4 ontbrekende tests, fixture verduidelijking

## Consistentiecheck
Passed — 1 verduidelijking (cleanup directory check). Geen contradicties gevonden.

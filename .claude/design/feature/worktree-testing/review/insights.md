# Insights

## Beslissingen

### Ronde 1
- Architect: Componentarchitectuur met WorktreeSetupService als orchestrator
- Architect: Isolatieprofiel als DB kolommen i.p.v. JSON blob
- Security: Cookie-validatie via nginx map regex
- Security: CLI commands valideren op record-integriteit i.p.v. web-RBAC
- UX/UI: Selector verborgen als geen worktrees, twee UX patronen (reload vs new tab)
- Developer: Cross-container nginx config via gedeeld Docker volume
- Developer: Shell commands via Symfony Process

### Ronde 2
- Architect: Cookie-routing (nginx map block) verplaatst naar fase 2 (was fase 3)
- Security: Nginx reload via signal file i.p.v. Docker socket (minder rechten)
- Security: Nginx map regex capture group gecorrigeerd
- Developer: Methode-naam `createDatabase()` → `setupDatabase()` geconsisteerd

## Consistentiecheck correcties

### Ronde 1
1. Service locaties naar `yii/services/worktree/` subdirectory
2. Ontbrekende test scenarios T2.8 en T3.8

### Ronde 2
1. Fase-tabel contradictie: cookie-routing naar fase 2
2. Nginx map regex capture group bug
3. Methode-naam inconsistentie
4. Test T4.9 voor signal file reload

## Open vragen
- Geen

## Blokkades
- Geen

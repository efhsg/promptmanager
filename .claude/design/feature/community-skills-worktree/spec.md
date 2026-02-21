# Feature: Community Skills Worktree

**Dependency:** `.claude/design/feature/worktree-service/` — moet eerst geïmplementeerd zijn.

## Samenvatting

Community skills installatie als eerste toepassing van de generieke `WorktreeService`. Voegt skill-specifieke logica toe: skills directory herstructurering, `npx skills add` integratie, SKILL.md scanning, en een dedicated UI sectie.

## User story

Als gebruiker wil ik community skills (bijv. `frontend-design` uit `anthropics/skills`) kunnen installeren per project in een geïsoleerde git worktree, zodat mijn main branch schoon blijft en ik skills vanuit PromptManager kan beheren.

## Scope

**In scope (deze spec):**
- Skills directory herstructurering (`skills/project/`)
- `CommunitySkillService` — skill installatie, `npx skills add`, SKILL.md scanning
- `CommunitySkillController` — skill-specifieke AJAX endpoints
- `_community_skills.php` — skill-specifieke UI sub-sectie in worktree kaart
- `community-skills.js` — skill-specifieke frontend interacties

**Dependency (aparte spec, al geïmplementeerd):**
- `WorktreeService` — generiek worktree CRUD, sync, status
- `project_worktree` tabel + `ProjectWorktree` model
- `WorktreeController` — generieke AJAX endpoints
- `worktree-manager.js` — generieke frontend

## Status

**Wacht op:** voltooiing `worktree-service` feature spec en implementatie.

**Spec nog te schrijven:** volledige specificatie wordt opgesteld zodra worktree-service is afgerond.

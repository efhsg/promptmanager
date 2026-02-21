# Community Skills Worktree — Analyse

## 1. Samenvatting

Doel: een **generiek patroon** voor het installeren van community skills (via `npx skills add`) in een geïsoleerde git worktree naast elk context project, met beheer vanuit PromptManager.

Drie fasen:
1. **Skills herstructurering** — projectskills naar `skills/project/`
2. **Worktree-patroon** — per-project worktree aanmaken en vullen
3. **PromptManager integratie** — worktrees beheren vanuit de UI

---

## 2. Beslissingen uit Q&A

| Vraag | Beslissing |
|-------|-----------|
| Worktree vs direct install | **Worktree** — per-project isolatie |
| Skill bron | **`anthropics/skills`** (`frontend-design`) als eerste case |
| Scope | **Generiek patroon** — meerdere bronnen per worktree |
| Global vs project | **Per-project** — `bes-lvs` kan andere skills hebben dan `TOA4` |
| PromptManager zelf | **In scope** — zelfde patroon als elk ander project |
| Skills directory structuur | **Optie 2** — projectskills naar `skills/project/`, community skills in `skills/<name>/` |

---

## 3. Bevindingen uit research

### 3.1 `npx skills add` installatiegedrag

Getest met `npx skills add vercel-labs/agent-skills --skill web-design-guidelines -a claude-code -y`:

| Wat | Waar |
|-----|------|
| Community skill content | `.claude/skills/<skill-name>/SKILL.md` |
| Lock file | `skills-lock.json` (project root) |
| Git status | Beide **untracked** (`??`) |

De `npx skills` CLI installeert altijd naar `.claude/skills/<name>/` — geen `--target` optie beschikbaar.

### 3.2 `anthropics/skills` — beschikbare skills

17 skills beschikbaar, waaronder:

| Skill | Relevant voor PromptManager |
|-------|----------------------------|
| `frontend-design` | Ja — production-grade frontend interfaces |
| `pdf` | Mogelijk — PDF export features |
| `mcp-builder` | Mogelijk — MCP server integratie |
| `skill-creator` | Ja — voor het schrijven van nieuwe projectskills |
| `doc-coauthoring` | Mogelijk — documentatie workflows |

### 3.3 Mountpoint-architectuur

```
PROJECTS_ROOT=/Users/erwin/projects
PATH_MAPPINGS='{"/Users/erwin/projects": "/projects", "/opt/promptmanager/dev": "/var/www/html"}'
```

Een project root is een subdirectory van een mountpoint. Een worktree-sibling valt onder hetzelfde mountpoint en is automatisch toegankelijk in de container.

| Project | Root (host) | Worktree (host) | Container |
|---------|------------|-----------------|-----------|
| bes-lvs | `~/projects/bes-lvs` | `~/projects/bes-lvs-skills` | `/projects/bes-lvs-skills` |
| TOA4 | `~/projects/TOA4` | `~/projects/TOA4-skills` | `/projects/TOA4-skills` |
| PromptManager | `/opt/promptmanager/dev` | `/opt/promptmanager/dev-skills` | Vereist parent mount |

### 3.4 Bestaande patronen in PromptManager

| Patroon | Waar | Herbruikbaar voor |
|---------|------|-------------------|
| Shell executie via `proc_open()` | `ClaudeCliProvider` | Git/npx commando's uitvoeren |
| Padvertaling host→container | `PathService::translatePath()` | Worktree pad afleiden |
| Workspace lifecycle | `AiWorkspaceProviderInterface` | Create/sync/delete worktree |
| Config status check | `ProjectController` (checkConfig) | Worktree status detecteren |
| AJAX response pattern | Controllers | `['success' => bool, 'message' => string, 'data' => mixed]` |

---

## 4. Skills directory herstructurering (Fase 1)

### 4.1 Nieuwe structuur

```
.claude/skills/
├── index.md                         # Registry (tracked) — verwijst naar beide typen
├── project/                         # Projectskills (tracked)
│   ├── frontend-design.md
│   ├── frontend-build.md
│   ├── model.md
│   ├── migration.md
│   ├── error-handling.md
│   ├── new-branch.md
│   ├── refactor.md
│   ├── review-changes.md
│   ├── triage-review.md
│   ├── improve-prompt.md
│   ├── custom-buttons.md
│   ├── onboarding.md
│   ├── orac-style.md
│   └── zen-style.md
├── frontend-design/                 # Community skill (untracked, alleen in worktree)
│   └── SKILL.md
└── pdf/                             # Community skill (untracked, alleen in worktree)
    └── SKILL.md
```

### 4.2 Referentie-impact

Alle referenties wijzigen van `skills/<name>.md` → `skills/project/<name>.md`.

| Bestand | Aantal referenties |
|---------|-------------------|
| `.claude/skills/index.md` | 18 |
| `.claude/rules/skill-routing.md` | 8 |
| `.claude/rules/workflow.md` | 5 |
| `.claude/rules/architecture.md` | 1 |
| `.claude/commands/*.md` (6 bestanden) | 10 |
| `.claude/skills/*.md` (cross-refs) | 5 |
| `.claude/prompts/*.md` (3 bestanden) | 9 |
| `CLAUDE.md` | 1 |
| **Totaal** | **~58** |

---

## 5. Worktree-patroon (Fase 2)

### 5.1 Workflow per project

```bash
# 1. Worktree aanmaken (eenmalig, vanuit project root op host)
cd <project-root>
git worktree add -b community-skills ../<project>-skills

# 2. Sync met main (projectskills + config overnemen)
cd ../<project>-skills
git merge main --no-edit

# 3. Community skills installeren (herhaalbaar, meerdere bronnen)
npx skills add anthropics/skills --skill frontend-design -a claude-code -y
npx skills add anthropics/skills --skill pdf -a claude-code -y

# 4. Verificatie
ls .claude/skills/project/        # Projectskills (uit main, tracked)
ls .claude/skills/frontend-design/ # Community skill (untracked)

# 5. Claude starten met community skills
claude                             # Vanuit de worktree
```

### 5.2 `sync.sh`

```bash
#!/usr/bin/env bash
# Sync community-skills worktree with main branch
git merge main --no-edit
```

Geplaatst in worktree root, uitvoerbaar (`chmod +x`).

### 5.3 Isolatiemodel

| Aspect | Main worktree | Skills worktree |
|--------|--------------|-----------------|
| Branch | `main` | `community-skills` |
| `.claude/skills/project/` | Projectskills (tracked) | Projectskills (via merge, tracked) |
| `.claude/skills/<name>/` | Leeg | Community skills (untracked) |
| `skills-lock.json` | Niet aanwezig | Aanwezig (untracked) |
| `claude` starten | Alleen projectskills | Project + community skills |

---

## 6. PromptManager integratie (Fase 3)

### 6.1 Concept

PromptManager biedt per project een **Community Skills** sectie waarmee de gebruiker worktrees kan beheren zonder de CLI te verlaten. De worktree-pad wordt afgeleid: `<root_directory>-skills`.

### 6.2 Padafleiding

Worktree-pad is **deterministisch** — geen extra database-kolom nodig:

```
root_directory:    /Users/erwin/projects/bes-lvs
worktree (host):   /Users/erwin/projects/bes-lvs-skills
worktree (container): /projects/bes-lvs-skills    ← via PathService::translatePath()
```

De container kan de worktree lezen/schrijven omdat de sibling onder hetzelfde `PROJECTS_ROOT` mountpoint valt.

### 6.3 Gebruikersflows

#### Flow 1: Worktree status bekijken

**Trigger:** gebruiker opent Project view/update pagina.

**Systeem checkt:**
1. Heeft het project een `root_directory`? → Zo niet: sectie verborgen
2. Is `root_directory` een git repo? → `git -C <path> rev-parse --git-dir`
3. Bestaat de worktree? → `is_dir(<root_directory>-skills)`
4. Welke community skills zijn geïnstalleerd? → scan `<worktree>/.claude/skills/*/SKILL.md`
5. Is de worktree in sync met main? → `git -C <worktree> log HEAD..main --oneline`

**Output:** statuskaart met worktree pad, sync status, en lijst geïnstalleerde skills.

#### Flow 2: Worktree aanmaken

**Precondities:**
- Project heeft `root_directory`
- `root_directory` is een git repo
- Worktree bestaat nog niet

**Stappen:**
1. Gebruiker klikt "Create Skills Worktree"
2. Systeem voert uit in container:
   ```bash
   git -C <root_directory> worktree add -b community-skills <root_directory>-skills
   cd <root_directory>-skills && git merge main --no-edit
   ```
3. Systeem maakt `sync.sh` aan in worktree root (`chmod +x`)
4. UI toont bevestiging met pad en instructies

#### Flow 3: Worktree synchroniseren

**Precondities:** worktree bestaat.

**Stappen:**
1. Gebruiker klikt "Sync with main"
2. Systeem voert uit: `git -C <worktree> merge main --no-edit`
3. Bij succes: UI toont "Synced" met commit count
4. Bij merge conflict: UI toont foutmelding + instructies voor handmatige resolutie

#### Flow 4: Community skill installeren

**Precondities:** worktree bestaat, Node.js beschikbaar.

**Stappen:**
1. Gebruiker voert in: bron (bijv. `anthropics/skills`) en skill naam (bijv. `frontend-design`)
2. Systeem voert uit in worktree:
   ```bash
   cd <worktree> && npx skills add <bron> --skill <naam> -a claude-code -y
   ```
3. UI toont resultaat en ververst skills lijst

#### Flow 5: Geïnstalleerde skills bekijken

**Precondities:** worktree bestaat.

**Stappen:**
1. Systeem scant `<worktree>/.claude/skills/*/SKILL.md`
2. Leest eerste regels van elk `SKILL.md` voor naam/beschrijving
3. Controleert `skills-lock.json` voor bronvermelding
4. UI toont tabel: skill naam, bron, beschrijving

#### Flow 6: Worktree verwijderen

**Precondities:** worktree bestaat.

**Stappen:**
1. Gebruiker klikt "Remove Skills Worktree" met bevestigingsdialoog
2. Systeem voert uit:
   ```bash
   git -C <root_directory> worktree remove <root_directory>-skills --force
   ```
3. UI toont bevestiging

### 6.4 Technisch ontwerp

#### Nieuw: `CommunitySkillService`

| Methode | Wat | Returns |
|---------|-----|---------|
| `getWorktreePath(Project $project)` | Leidt worktree pad af | `?string` (null als geen root_dir) |
| `getWorktreeStatus(Project $project)` | Checkt bestaan, sync status, skills | `WorktreeStatus` DTO |
| `createWorktree(Project $project)` | Maakt worktree + sync.sh | `bool` |
| `syncWorktree(Project $project)` | Draait `git merge main` | `SyncResult` DTO |
| `installSkill(Project $project, string $source, string $skill)` | Draait `npx skills add` | `bool` |
| `listInstalledSkills(Project $project)` | Scant worktree op SKILL.md | `CommunitySkill[]` |
| `removeWorktree(Project $project)` | Verwijdert worktree | `bool` |

**Dependencies:**
- `PathService` — padvertaling host→container
- Shell executie via `proc_open()` (bestaand patroon uit `ClaudeCliProvider`)

#### Nieuw: DTOs

```
WorktreeStatus {
    bool $exists
    bool $isGitRepo
    string $containerPath
    string $hostPath
    int $behindMainCount       // commits achter op main
    CommunitySkill[] $skills
}

CommunitySkill {
    string $name               // directory naam
    string $source             // uit skills-lock.json
    string $description        // eerste regel SKILL.md
}

SyncResult {
    bool $success
    int $commitsMerged
    ?string $errorMessage      // bij merge conflict
}
```

#### Controller integratie

Twee opties:

**Optie A — In bestaande `ProjectController`:**
- Nieuwe AJAX actions: `actionWorktreeStatus`, `actionWorktreeCreate`, `actionWorktreeSync`, `actionSkillInstall`, `actionWorktreeRemove`
- Voegt 5 actions toe aan een al uitgebreide controller

**Optie B — Nieuw `CommunitySkillController`:**
- Eigen controller met dedicated RBAC (ProjectOwnerRule)
- Schoner, maar extra controller voor relatief weinig actions

**Aanbeveling:** Optie B — een nieuw `CommunitySkillController`. De verantwoordelijkheid (worktree + skills beheer) is duidelijk afgebakend en verschilt van project CRUD.

#### View integratie

Nieuwe sectie op de **Project view** pagina (`views/project/view.php`), of een eigen pagina bereikbaar via tab/link:

```
┌─────────────────────────────────────────────────┐
│ Community Skills                    [Create] btn │
├─────────────────────────────────────────────────┤
│ Worktree: /projects/bes-lvs-skills              │
│ Status: ✓ In sync with main                     │
│                                          [Sync] │
├─────────────────────────────────────────────────┤
│ Installed Skills:                               │
│ ┌──────────────────┬─────────────────┬────────┐ │
│ │ Name             │ Source          │ Action │ │
│ ├──────────────────┼─────────────────┼────────┤ │
│ │ frontend-design  │ anthropics/..   │   ⓘ   │ │
│ │ pdf              │ anthropics/..   │   ⓘ   │ │
│ └──────────────────┴─────────────────┴────────┘ │
├─────────────────────────────────────────────────┤
│ Install: [source______] [skill______] [Install] │
├─────────────────────────────────────────────────┤
│ Usage: cd /projects/bes-lvs-skills && claude    │
└─────────────────────────────────────────────────┘
```

### 6.5 RBAC

Hergebruik `ProjectOwnerRule` — alleen de eigenaar van het project mag worktrees beheren. Geen nieuwe RBAC rules nodig.

### 6.6 Wat er NIET in de database komt

Alles is **filesystem-derived**:
- Worktree bestaat? → `is_dir()`
- Welke skills? → directory scan
- Sync status? → `git log`
- Skill bron? → `skills-lock.json`

Geen migraties, geen nieuwe tabellen. De enige "state" is de worktree op het filesystem.

---

## 7. Edge cases

| Scenario | Impact | Mitigatie |
|----------|--------|-----------|
| **Project zonder `root_directory`** | Geen worktree mogelijk | Sectie verborgen in UI |
| **Root directory is geen git repo** | `git worktree add` faalt | UI toont melding; feature uitgeschakeld |
| **Worktree pad niet beschikbaar in container** | Alle operaties falen | PathService check + duidelijke foutmelding |
| **`git clean -fd` in worktree** | Verwijdert untracked community skills | Herinstalleer via UI |
| **Merge conflict bij sync** | `sync.sh`/UI sync faalt | Toon foutmelding + CLI instructies |
| **Worktree handmatig verwijderd** | PromptManager detecteert ontbreken | Toont "Create" knop opnieuw |
| **PromptManager parent mount** | Sibling niet bereikbaar | Check pad bereikbaarheid; toon configuratie-instructie |
| **Meerdere bronnen, zelfde skill naam** | Tweede install overschrijft eerste | Toon waarschuwing in UI |
| **`npx` niet beschikbaar** | Installatie faalt | Check `which npx` bij status; toon foutmelding |
| **Zeer grote repo** | Worktree aanmaken duurt lang | Async uitvoeren of timeout verhogen |
| **Community skill verwijst naar `skills/project/`** | Onwaarschijnlijk — community skills zijn self-contained | Geen actie nodig |

---

## 8. Acceptatiecriteria

### Fase 1: Skills herstructurering

| # | Criterium | Verificatie |
|---|-----------|-------------|
| AC1 | Projectskills verplaatst naar `skills/project/` | `ls .claude/skills/project/*.md` toont 14 bestanden |
| AC2 | `index.md` blijft op `skills/index.md` | `test -f .claude/skills/index.md` |
| AC3 | Alle referenties bijgewerkt naar `skills/project/` | Geen match op `skills/[a-z].*\.md` buiten `skills/project/` en `skills/index.md` |
| AC4 | Slash commands werken ongewijzigd | Handmatige test |

### Fase 2: Worktree-patroon

| # | Criterium | Verificatie |
|---|-----------|-------------|
| AC5 | Worktree aangemaakt als sibling `<project>-skills` | `git worktree list` toont twee entries |
| AC6 | Worktree op branch `community-skills` | `git -C ../<project>-skills branch --show-current` |
| AC7 | Na sync: projectskills aanwezig in `skills/project/` | `ls ../<project>-skills/.claude/skills/project/*.md` |
| AC8 | Community skill geïnstalleerd | `ls ../<project>-skills/.claude/skills/frontend-design/SKILL.md` |
| AC9 | Community skills untracked | `git -C ../<project>-skills status` toont `??` of niets |
| AC10 | `sync.sh` aanwezig en uitvoerbaar | `test -x ../<project>-skills/sync.sh` |
| AC11 | Main branch blijft schoon | `git status` in main toont geen artifacts |

### Fase 3: PromptManager integratie

| # | Criterium | Verificatie |
|---|-----------|-------------|
| AC12 | Project view toont worktree status | Sectie zichtbaar als `root_directory` ingesteld |
| AC13 | Worktree aanmaken vanuit UI | Klik "Create" → worktree bestaat |
| AC14 | Worktree synchen vanuit UI | Klik "Sync" → merge uitgevoerd |
| AC15 | Skill installeren vanuit UI | Invoer bron + naam → skill verschijnt in lijst |
| AC16 | Geïnstalleerde skills zichtbaar | Tabel toont naam, bron, beschrijving |
| AC17 | Worktree verwijderen vanuit UI | Klik "Remove" + bevestiging → worktree weg |
| AC18 | Alleen project-eigenaar heeft toegang | Andere user krijgt 403 |
| AC19 | Feature verborgen zonder `root_directory` | Geen sectie zichtbaar |
| AC20 | Foutmeldingen bij problemen | Merge conflict, npx ontbreekt, pad onbereikbaar → leesbare melding |

---

## 9. Implementatievolgorde

### Fase 1 — Skills herstructurering (main branch)

| Stap | Wat |
|------|-----|
| 1.1 | Maak `skills/project/` directory |
| 1.2 | Verplaats 14 skill `.md` bestanden naar `skills/project/` |
| 1.3 | Update alle ~58 referenties in config/commands/prompts/rules |
| 1.4 | Update `index.md` structuur |
| 1.5 | Commit |

### Fase 2 — Worktree-patroon (handmatig, per project)

| Stap | Wat |
|------|-----|
| 2.1 | Maak worktree aan vanuit project root |
| 2.2 | Sync met main |
| 2.3 | Installeer community skills |
| 2.4 | Maak `sync.sh` uitvoerbaar |
| 2.5 | Verifieer Claude sessie vanuit worktree |

### Fase 3 — PromptManager UI (feature branch)

| Stap | Wat |
|------|-----|
| 3.1 | `CommunitySkillService` met padafleiding en shell executie |
| 3.2 | DTOs: `WorktreeStatus`, `CommunitySkill`, `SyncResult` |
| 3.3 | `CommunitySkillController` met AJAX actions |
| 3.4 | RBAC: hergebruik `ProjectOwnerRule` |
| 3.5 | View: community skills sectie op project pagina |
| 3.6 | JS: AJAX calls voor create/sync/install/remove |
| 3.7 | Unit tests voor `CommunitySkillService` |

---

## 10. Buiten scope

- Automatische sync (cron/webhook)
- Skill marketplace / browsable catalog in UI
- Skill versioning (altijd latest)
- `.gitignore` wijzigingen in main branch (niet nodig)
- Community skills committen op de `community-skills` branch

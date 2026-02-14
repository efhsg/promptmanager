# Spec Review Workflow

Analyseer een user story en schrijf een specificatie die door meerdere rollen wordt verbeterd tot minimaal een 8/10.

## Invoer

- **FEATURE**: GEN:{{Feature Name}}
- **USER_STORY**: GEN:{{User Story}}
- **DESIGN_DIR**: `.claude/design/[FEATURE]`
- **AGENT_MEMORY**: `.claude/design/[FEATURE]/review`

## Before you start

1. Lees `.claude/codebase_analysis.md` voor domeincontext

2. Verifieer dat alle rol-files bestaan:
   - `.claude/prompts/roles/Analist.md`
   - `.claude/prompts/roles/Architect.md`
   - `.claude/prompts/roles/Security.md`
   - `.claude/prompts/roles/UX-UI designer.md`
   - `.claude/prompts/roles/Front-end Developer.md`
   - `.claude/prompts/roles/Developer.md`
   - `.claude/prompts/roles/Tester.md`

   **STOP** als een file ontbreekt — meld aan gebruiker welke file mist.

3. Als [AGENT_MEMORY] **niet** bestaat:
   1. Maak directory [DESIGN_DIR] aan
   2. Maak directory [AGENT_MEMORY] aan
   3. Maak memory files:
      - `context.md` — doel, scope, user story
      - `todos.md` — alle stappen (zie template hieronder)
      - `insights.md` — beslissingen, open vragen, blokkades

4. Als [AGENT_MEMORY] **wel** bestaat:
   - Lees `todos.md` en `insights.md` volledig
   - Ga verder met de eerste niet-afgevinkte stap

## todos.md template

```markdown
# Review Todos

## Fase 1: Initieel ontwerp
- [ ] Analist: spec.md schrijven

## Fase 2: Reviews
- [ ] Architect: review (score >= 8)
- [ ] Security: review (score >= 8)
- [ ] UX/UI Designer: review (score >= 8)
- [ ] Front-end Developer: review (score >= 8)
- [ ] Developer: review (score >= 8)
- [ ] Tester: review (score >= 8)

## Fase 3: Afsluiting
- [ ] Finale samenvatting presenteren
```

---

## Per stap

1. Lees de relevante rol-file
2. Lees `spec.md` (behalve bij eerste stap — dan schrijf je deze)
3. Voer de stap uit volgens de rol
4. Bij open vragen voor gebruiker: **STOP**, noteer in `insights.md`, vraag gebruiker
5. Bij blokkade: **STOP**, noteer in `insights.md`, vraag gebruiker
6. Vink af in `todos.md` **voordat** je aan de volgende stap begint
7. Schrijf review naar `reviews.md` (bij review-stappen)

---

## Fase 1: Initieel ontwerp (Analist)

**Rol**: `.claude/prompts/roles/Analist.md`

### Stappen

1. Analyseer de user story:
   - Identificeer de hoofdfunctionaliteit
   - Identificeer betrokken entiteiten
   - Identificeer gebruikersflows
2. Schrijf `spec.md` naar [DESIGN_DIR]

### spec.md structuur

```markdown
# Feature: [FEATURE]

## Samenvatting
{1-2 zinnen: wat doet deze feature?}

## User story
[USER_STORY]

## Functionele requirements

### FR-1: {Requirement titel}
- Beschrijving: {wat moet het systeem doen}
- Acceptatiecriteria:
  - [ ] {Meetbaar criterium}

### FR-N: {herhaal per requirement}

## Gebruikersflow
1. {Stap}
2. {Stap}

## Edge cases
| Case | Gedrag |
|------|--------|
| {situatie} | {verwacht gedrag} |

## Entiteiten en relaties
{Welke models/tabellen zijn betrokken, welke nieuwe velden/relaties}

## Open vragen
- {Vragen die beantwoord moeten worden, of "Geen"}

## UI/UX overwegingen
{Globale UI-aanpak — details komen van UX-reviewer}

## Technische overwegingen
{Globale technische aanpak — details komen van reviewers}
```

### Na schrijven

**STOP** als er open vragen zijn — noteer in `insights.md`, vraag gebruiker.

Toon aan de gebruiker:
```
## Spec geschreven

Bestand: [DESIGN_DIR]/spec.md

### Open vragen
{Lijst open vragen, indien van toepassing}

[A] Beantwoord vragen en ga door naar review
[S] Sla review over, ga direct naar implementatie
```

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**

Vink af in `todos.md`: `- [x] Analist: spec.md schrijven`

---

## Fase 2: Review cyclus

### Reviewvolgorde

1. **Architect** — `.claude/prompts/roles/Architect.md`
2. **Security** — `.claude/prompts/roles/Security.md`
3. **UX/UI Designer** — `.claude/prompts/roles/UX-UI designer.md`
4. **Front-end Developer** — `.claude/prompts/roles/Front-end Developer.md`
5. **Developer** — `.claude/prompts/roles/Developer.md`
6. **Tester** — `.claude/prompts/roles/Tester.md`

### Per reviewer

1. Laad de rol-file
2. Lees `spec.md`
3. Beoordeel vanuit je rol-perspectief

#### Scoor op criteria

| Criterium | Score (1-10) |
|-----------|--------------|
| Volledigheid | |
| Duidelijkheid | |
| Implementeerbaarheid | |
| Consistentie | |
| **Totaal** | |

#### Bepaal actie

**Als score < 8:**
1. Identificeer verbeterpunten
2. **STOP** als er vragen zijn — noteer in `insights.md`, vraag gebruiker
3. Verbeter `spec.md`
4. Schrijf review naar `reviews.md`
5. Vink af in `todos.md`

**Als score < 8 maar geen verbeterpunten:**
1. **STOP** — noteer in `insights.md`: "Score {X}/10 maar geen concrete verbeterpunten"
2. Vraag gebruiker: "Wat ontbreekt er volgens jou?"

**Als score >= 8:**
1. Schrijf review naar `reviews.md`
2. Vink af in `todos.md`
3. Ga door naar volgende reviewer

#### reviews.md entry

```markdown
## Review: {Rol} — {Datum}

### Score: {X}/10

### Goed
- {Wat goed is}

### Verbeterd
- {Wat je hebt aangepast, of "Geen"}

### Nog open
- {Vragen/punten, of "Geen"}
```

---

## Fase 3: Afsluiting

**Eindconditie**: Alle 6 reviews afgevinkt in `todos.md`.

1. Lees finale `spec.md`
2. Lees `reviews.md`
3. Presenteer samenvatting:

```
## Spec Review Voltooid

### Finale scores
| Reviewer | Score |
|----------|-------|
| Architect | X/10 |
| Security | X/10 |
| UX/UI Designer | X/10 |
| Front-end Developer | X/10 |
| Developer | X/10 |
| Tester | X/10 |

### Status
{Alle scores >= 8: Klaar voor implementatie}
{Anders: Lijst openstaande punten uit insights.md}

### Bestanden
- Specificatie: [DESIGN_DIR]/spec.md
- Reviews: [DESIGN_DIR]/reviews.md
- Insights: [AGENT_MEMORY]/insights.md

[I] Start implementatie
[R] Nog een review ronde
[E] Handmatig bewerken
```

4. Vink af in `todos.md`: `- [x] Finale samenvatting presenteren`
5. Noteer eindresultaat in `insights.md`

---

## Stopregels

**VERPLICHT** — Stop, noteer in `insights.md`, en vraag gebruiker bij:

- Open vragen die de gebruiker moet beantwoorden
- Onduidelijkheid over scope of prioriteit
- Conflict tussen requirements
- Keuzes die de gebruiker moet maken

**Nooit** doorgaan met aannames over gebruikersvoorkeuren.

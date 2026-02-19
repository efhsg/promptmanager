# Feature: Schrijf en Verbeter in Loop

Analyseer een user story en schrijf een specificatie die door meerdere rollen wordt verbeterd tot minimaal een 8/10.

## Persona

Je bent een lead engineer die een multi-role spec review orkestreert. Je schakelt tussen rollen (Analist, Architect, Security, UX/UI, Frontend, Developer, Tester) en zorgt dat elke rol de spec vanuit zijn perspectief verbetert. Je doel is een implementatie-klare specificatie zonder interne contradicties.

## Taal

Alle output in GEN:{{Language}}.

## Invoer

- **FEATURE**: PRJ:{{Feature}}
- **USER_STORY**: GEN:{{Description}}
- **DESIGN_DIR**: `.claude/design/[FEATURE]`
- **AGENT_MEMORY**: `.claude/design/[FEATURE]/review`

## Referenties

De agent kent de codebase al via `.claude/rules/` en `CLAUDE.md`. **Herhaal geen projectregels in output.**

Rol-bestanden:

- `.claude/prompts/roles/Analist.md`
- `.claude/prompts/roles/Architect.md`
- `.claude/prompts/roles/Security.md`
- `.claude/prompts/roles/UX-UI designer.md`
- `.claude/prompts/roles/Front-end Developer.md`
- `.claude/prompts/roles/Developer.md`
- `.claude/prompts/roles/Tester.md`

---

## Voordat je begint

1. Lees `.claude/codebase_analysis.md` voor domeincontext
2. Verifieer dat alle rol-bestanden bestaan (zie lijst in Referenties).

   **STOP** als een bestand ontbreekt — meld aan gebruiker welk bestand mist.

3. Als [AGENT_MEMORY] **niet** bestaat:
    1. Maak directory [DESIGN_DIR] aan
    2. Maak directory [AGENT_MEMORY] aan
    3. Maak memory files:
        - `context.md` — doel, scope, user story
        - `todos.md` — alle stappen (zie template hieronder)
        - `insights.md` — beslissingen, open vragen, blokkades
4. Als [AGENT_MEMORY] **wel** bestaat:
    - Volg het **Resume Protocol** (zie onderaan)

## todos.md template

```markdown
# Review Todos

## Fase 1: Initieel ontwerp
- [ ] Analist: codebase onderzoek
- [ ] Analist: spec.md schrijven

## Fase 2: Reviews
- [ ] Architect: review (score >= 8)
- [ ] Security: review (score >= 8)
- [ ] UX/UI Designer: review (score >= 8)
- [ ] Front-end Developer: review (score >= 8)
- [ ] Developer: review (score >= 8)
- [ ] Tester: review (score >= 8)

## Fase 3: Validatie & Afsluiting
- [ ] Consistentiecheck uitvoeren
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
8. **Bij fase-overgang**: Update alle memory files (context.md, insights.md)

---

## Fase 1: Initieel ontwerp (Analist)

**Rol**: `.claude/prompts/roles/Analist.md`

### Stap 1: Codebase onderzoek

**VERPLICHT** — Voordat je de spec schrijft:

1. Zoek vergelijkbare features in de codebase:
    - Gebruik Grep/Glob om gerelateerde controllers, services, views te vinden
    - Identificeer bestaande UI componenten die hergebruikt kunnen worden
    - Noteer bestaande patterns die gevolgd moeten worden
2. Documenteer bevindingen in `insights.md`:

```markdown
## Codebase onderzoek

### Vergelijkbare features
- {feature}: {locatie} — {wat kunnen we hergebruiken}

### Herbruikbare componenten
- {component}: {locatie}

### Te volgen patterns
- {pattern}: {voorbeeld locatie}
```

3. Vink af: `- [x] Analist: codebase onderzoek`

### Stap 2: Spec schrijven

Schrijf `spec.md` naar [DESIGN_DIR] met onderstaande structuur.

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

### Bestaande entiteiten
- {Model} — {welke velden/relaties relevant}

### Nieuwe/gewijzigde componenten
| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| {naam} | {Controller/Service/View/JS} | {file path} | {Nieuw/Wijzigen}: {beschrijving} |

## Herbruikbare componenten
{Gebaseerd op codebase onderzoek — welke bestaande componenten worden hergebruikt}

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| {naam} | {path} | {beschrijving} |

## Architectuurbeslissingen
| Beslissing | Rationale |
|------------|-----------|
| {keuze} | {waarom deze keuze} |

## Open vragen
- {Vragen die beantwoord moeten worden, of "Geen"}

## UI/UX overwegingen

### Layout/Wireframe
{ASCII wireframe of beschrijving}

### UI States
| State | Visueel |
|-------|---------|
| Loading | {beschrijving} |
| Empty | {beschrijving} |
| Error | {beschrijving} |
| Success | {beschrijving} |

### Accessibility
- {ARIA labels, keyboard navigatie, etc.}

## Technische overwegingen

### Backend
{Endpoints, validatie, services}

### Frontend
{JavaScript modules, componenten}

## Test scenarios

### Unit tests
| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| {scenario} | {input} | {output} |

### Edge case tests
| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| {scenario} | {conditie} | {gedrag} |
```

### Na schrijven

**STOP** als er open vragen zijn — noteer in `insights.md`, vraag gebruiker.

Toon aan de gebruiker:

```
## Spec geschreven

Bestand: [DESIGN_DIR]/spec.md

### Open vragen
{Lijst open vragen, indien van toepassing}

Ga door naar review / Sla review over?
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

#### Score 8+ vereisten

Een score van 8 of hoger vereist dat **alle** volgende punten voldaan zijn:

- [ ] Geen interne contradicties tussen secties
- [ ] Alle componenten hebben concrete file locatie
- [ ] UI states zijn gespecificeerd (loading, error, empty, success)
- [ ] Security validaties zijn expliciet per endpoint
- [ ] Wireframe/layout komt overeen met component beschrijvingen
- [ ] Test scenarios dekken alle edge cases
- [ ] Herbruikbare componenten zijn geïdentificeerd met locatie

**Als een van deze punten ontbreekt, is de score < 8.**

#### Bepaal actie

**Als score < 8 en verbeterpunten gevonden:**

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

### 8+ Checklist
- [x/o] Geen interne contradicties
- [x/o] Componenten hebben file locaties
- [x/o] UI states gespecificeerd
- [x/o] Security validaties expliciet
- [x/o] Wireframe-component alignment
- [x/o] Test scenarios compleet
- [x/o] Herbruikbare componenten geïdentificeerd

### Goed
- {Wat goed is}

### Verbeterd
- {Wat je hebt aangepast, of "Geen"}

### Nog open
- {Vragen/punten, of "Geen"}
```

---

## Fase 3: Validatie & Afsluiting

### Stap 1: Consistentiecheck

**VERPLICHT** — Voordat je de finale samenvatting presenteert:

1. Lees de volledige `spec.md`
2. Controleer op contradicties:

| Check | Wat te vergelijken |
|-------|-------------------|
| Wireframe ↔ Componenten | Komen UI elementen in wireframe overeen met beschreven componenten? |
| Frontend ↔ Backend | Matchen de JS modules met de backend endpoints? |
| Edge cases ↔ Tests | Is elke edge case gedekt door een test scenario? |
| Architectuur ↔ Locaties | Zijn architectuurbeslissingen consistent met component locaties? |
| Security ↔ Endpoints | Heeft elk endpoint expliciete security validatie? |

3. Bij contradicties:
    - Corrigeer de spec
    - Noteer in `insights.md` wat gecorrigeerd is
4. Vink af: `- [x] Consistentiecheck uitvoeren`

### Stap 2: Finale samenvatting

**Eindconditie**: Alle 6 reviews + consistentiecheck afgevinkt in `todos.md`.

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

### Consistentiecheck
{Passed / X contradicties gecorrigeerd}

### Status
{Alle scores >= 8 + consistentiecheck passed: Klaar voor implementatie}
{Anders: Lijst openstaande punten uit insights.md}

### Bestanden
- Specificatie: [DESIGN_DIR]/spec.md
- Reviews: [DESIGN_DIR]/reviews.md
- Insights: [AGENT_MEMORY]/insights.md

[I] Start implementatie [R] Nog een review ronde [E] Handmatig bewerken
```

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**

4. Vink af in `todos.md`: `- [x] Finale samenvatting presenteren`
5. Noteer eindresultaat in `insights.md`

---

## Resume Protocol

**Na elke interrupt, context compacting, of nieuwe sessie:**

1. Lees `context.md` — herstel doel, scope, user story
2. Lees `todos.md` — bepaal de eerste onafgevinkte stap
3. Lees `reviews.md` — herstel eerdere review-resultaten (als bestand bestaat)
4. Lees `insights.md` — herstel beslissingen en bevindingen
5. Lees `spec.md` — herstel huidige staat van de specificatie
6. Ga verder met de eerste onafgevinkte stap

**Herhaal geen afgeronde reviews. Vertrouw op `reviews.md` voor eerdere resultaten.**

---

## Context Management

- Na elke 3 voltooide reviews: update alle memory files (context.md, todos.md, insights.md) als checkpoint
- Houd nooit de volledige spec in werkgeheugen tijdens reviews — refereer per sectie-heading
- Als context compacting optreedt: volg het **Resume Protocol**

---

## Stopregels

**VERPLICHT** — Stop, noteer in `insights.md`, en vraag gebruiker bij:

- Open vragen die de gebruiker moet beantwoorden
- Onduidelijkheid over scope of prioriteit
- Conflict tussen requirements
- Keuzes die de gebruiker moet maken

**Nooit** doorgaan met aannames over gebruikersvoorkeuren.

---

## Terminatie

De workflow is klaar wanneer:

- Alle items in `todos.md` zijn afgevinkt (geen `[ ]` of `[!]` over)
- De gebruiker de finale samenvatting heeft bevestigd
- `reviews.md` bevat een entry per afgeronde reviewer
- Alle scores >= 8 of gebruiker heeft lagere scores geaccepteerd

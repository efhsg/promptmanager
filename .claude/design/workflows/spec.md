# Workflow Recipes — Functionele Specificatie

## Overzicht

PromptManager krijgt een **Workflow-laag**: een systeem van configureerbare, meerstaps-recepten die bestaande PromptManager-functionaliteit (transcripts ophalen, prompts genereren, AI-verwerking, scratch pads) orkestreren tot herbruikbare workflows. In de eerste versie worden **vaste recepten** (YouTube Extractor, Website Manager) geleverd met een gedeelde architectuur die later uitbreidbaar is naar volledig door de gebruiker configureerbare workflows.

**Waarom:** PromptManager is een krachtige toolkit, maar de gebruiker moet nu zelf de stappen orkestreren (transcript ophalen → handmatig kopiëren → prompt invullen → genereren). Workflows automatiseren deze ketens en maken PromptManager inzetbaar voor concrete use cases.

---

## Domeinmodel

### Nieuwe entiteiten

```
User
  └─ WorkflowRecipe (1:N)
        ├─ name, description, type (enum)
        ├─ is_system (bool) — systeemrecepten vs. user-defined (toekomst)
        └─ WorkflowStep (1:N, geordend)
              ├─ order, step_type (enum), label
              ├─ config (JSON) — stap-specifieke configuratie
              ├─ requires_approval (bool) — stap-voor-stap of automatisch
              └─ (verwijst naar input/output via WorkflowRun)

User
  └─ WorkflowRun (1:N)
        ├─ recipe_id (FK → WorkflowRecipe)
        ├─ project_id (FK → Project, nullable)
        ├─ status (enum: pending, running, paused, completed, failed, cancelled)
        ├─ current_step (int) — huidige stap-index
        ├─ started_at, completed_at
        └─ WorkflowStepResult (1:N)
              ├─ step_order (int) — correspondeert met WorkflowStep.order
              ├─ status (enum: pending, running, completed, failed, skipped)
              ├─ input_data (LONGTEXT JSON) — input voor deze stap
              ├─ output_data (LONGTEXT JSON) — resultaat van deze stap
              ├─ error_message (TEXT, nullable)
              ├─ started_at, completed_at
              └─ scratch_pad_id (FK → ScratchPad, nullable) — optionele link naar opgeslagen resultaat
```

### Relatie tot bestaande entiteiten

| Bestaande entiteit | Relatie | Toelichting |
|---|---|---|
| Project | WorkflowRun.project_id | Workflow draait in project-context (optioneel) |
| ScratchPad | WorkflowStepResult.scratch_pad_id | Stapresultaten kunnen persistent worden opgeslagen als ScratchPad |
| PromptTemplate | Via WorkflowStep.config | Een stap kan verwijzen naar een template voor prompt-generatie |
| Context | Via WorkflowStep.config | Een stap kan contexten meegeven aan prompt-generatie |
| Field | Via WorkflowStep.config | Een stap kan veldwaarden specificeren |

### Entity graph impact

- **Nieuwe entiteiten:** WorkflowRecipe, WorkflowStep, WorkflowRun, WorkflowStepResult
- **Bestaande entiteiten:** Geen schemawijzigingen. Relaties zijn via FK (project_id, scratch_pad_id) of via config-JSON (template_id, context_ids, field_values)
- **RBAC:** Nieuw: `WorkflowRecipeOwnerRule`, `WorkflowRunOwnerRule`

---

## Huidige situatie (wat er al bestaat)

### YouTube transcript-flow

**Pad:** `ScratchPadController::actionImportYoutube` → `YouTubeTranscriptService`

Huidige flow: gebruiker plakt YouTube-URL in modal → backend haalt transcript op via `ytx.py` → converteert naar Quill Delta → maakt ScratchPad aan → redirect naar view.

**Beperking:** Eenstaps-pipeline. Na import moet de gebruiker handmatig naar Claude navigeren voor vertaling of samenvatting.

### Claude CLI-integratie

**Pad:** `ScratchPadController::actionRunClaude` / `actionStreamClaude` → `ClaudeCliService`

Huidige flow: gebruiker opent Claude-view vanuit ScratchPad → stuurt prompt → ontvangt streaming response → response wordt opgeslagen in `ScratchPad.response`.

**Beperking:** Geen koppeling met vorige acties. Gebruiker moet handmatig kopiëren tussen stappen.

### Prompt-generatie

**Pad:** `PromptInstanceController::actionGenerateFinalPrompt` → `PromptGenerationService`

Huidige flow: gebruiker selecteert template + contexten → vult velden in → genereert prompt → kan opslaan als PromptInstance.

**Beperking:** Client-side state machine (accordion). Niet beschikbaar als API voor automatisering.

---

## Nieuw/gewijzigd gedrag

### Concept: Workflow = Recept + Uitvoering

Een **WorkflowRecipe** definieert de stappen (wat moet er gebeuren). Een **WorkflowRun** is een concrete uitvoering van dat recept (met tussenresultaten en status).

### Staptypes (eerste versie)

| Step Type | Beschrijving | Input | Output |
|---|---|---|---|
| `youtube_transcript` | Haal transcript op van YouTube-video | `video_url` (gebruikerinvoer) | Quill Delta met transcript + metadata |
| `ai_transform` | Stuur content naar Claude met instructie | `content` (vorige stap of gebruikerinvoer), `system_prompt`, `model`, `options` | Quill Delta met AI-response |
| `prompt_generate` | Genereer prompt via bestaand template | `template_id`, `context_ids`, `field_values` | Quill Delta met gegenereerde prompt |
| `save_scratch_pad` | Sla resultaat op als ScratchPad | `content` (vorige stap), `name`, `project_id` | scratch_pad_id |
| `user_input` | Wacht op handmatige invoer van gebruiker | UI-prompt met instructie | Quill Delta met gebruikersinvoer |
| `url_fetch` | Haal webpagina-inhoud op via URL | `url` (gebruikerinvoer) | Quill Delta met pagina-content (geconverteerd van HTML) |

### Data flow tussen stappen

Elke stap produceert `output_data` (opgeslagen als JSON in `WorkflowStepResult`). De volgende stap kan via een simpele referentie de output van een eerdere stap als input gebruiken:

```
Stap 2 config: { "content_source": "step:1" }  → pakt output_data van stap 1
Stap 3 config: { "content_source": "step:2" }  → pakt output_data van stap 2
Stap 3 config: { "content_source": "user" }     → wacht op gebruikerinvoer
```

Tussenresultaten zijn **altijd gepersisteerd** in `WorkflowStepResult.output_data`. Dit maakt het mogelijk om:
- Een workflow te pauzeren en later te hervatten
- Tussenresultaten te bekijken en handmatig te bewerken
- Na een fout de mislukte stap opnieuw uit te voeren

### Goedkeuringsmodel per stap

Elke `WorkflowStep` heeft een `requires_approval` vlag:

| `requires_approval` | Gedrag |
|---|---|
| `true` | Workflow pauzeert na deze stap. Gebruiker ziet resultaat, kan bewerken, en klikt "Doorgaan" of "Annuleren". |
| `false` | Workflow gaat automatisch door naar de volgende stap. |

Bij een **automatische stap** wordt de output direct doorgegeven. Bij een **goedkeuringsstap** kan de gebruiker de output bewerken voordat de volgende stap start (de bewerkte versie wordt de input voor de volgende stap).

### Vaste recepten (eerste versie)

#### Recept 1: YouTube Extractor

| Stap | Type | Label | Approval | Config |
|---|---|---|---|---|
| 1 | `youtube_transcript` | Transcript ophalen | true | `{}` — gebruiker voert URL in |
| 2 | `save_scratch_pad` | Opslaan als ScratchPad | false | `{ "name_source": "auto", "content_source": "step:1" }` |
| 3 | `ai_transform` | Vertalen of samenvatten | true | `{ "content_source": "step:1", "instruction_source": "user" }` — gebruiker kiest taal + doel |
| 4 | `save_scratch_pad` | Resultaat opslaan | false | `{ "name_source": "auto", "content_source": "step:3" }` |

**Gebruikersinvoer bij stap 3:** Selectie van:
- **Actie:** Vertalen / Samenvatten / Vrije instructie
- **Taal:** Dropdown (Nederlands, Engels, Duits, Frans, Spaans, of vrij tekstveld)
- **Doelgroep/doel:** (alleen bij samenvatten) Vrij tekstveld

**Alternatief pad:** Na stap 2 kan de gebruiker kiezen om te stoppen (alleen transcript opslaan) of door te gaan met stap 3-4.

#### Recept 2: Website Manager (Fase 1 — alleen lezen + herschrijven)

| Stap | Type | Label | Approval | Config |
|---|---|---|---|---|
| 1 | `url_fetch` | Pagina ophalen | true | `{}` — gebruiker voert URL in |
| 2 | `user_input` | Rollen selecteren | true | `{ "prompt": "Selecteer rollen voor herschrijven", "options": ["Tekstschrijver", "SEO Specialist", "Sales Manager", "UX Designer"] }` |
| 3 | `ai_transform` | Pagina herschrijven | true | `{ "content_source": "step:1", "roles_source": "step:2", "instruction": "Herschrijf vanuit geselecteerde rollen" }` |
| 4 | `save_scratch_pad` | Suggesties opslaan | false | `{ "content_source": "step:3" }` |

**Toekomstige fase:** Stap 5 zou "Wijzigingen implementeren" zijn (schrijven naar website). Dit valt buiten scope van deze versie maar de architectuur moet hier rekening mee houden door het staptypesysteem uitbreidbaar te houden.

---

## UX-flow

### Navigatie

Nieuw menu-item in de top-navigatie onder het **Manage**-dropdown:
- "Workflows" → `/workflow/index`

### Overzichtspagina (`/workflow/index`)

- Lijst van beschikbare recepten (cards/grid)
- Elke kaart toont: naam, beschrijving, aantal stappen, "Start" knop
- Sectie "Lopende workflows" met actieve/gepauzeerde runs (als die bestaan)
- Sectie "Afgeronde workflows" met geschiedenis

### Workflow starten

1. Gebruiker klikt "Start" op een recept
2. **Project selectie** (optioneel) — dropdown met projecten van de gebruiker
3. Workflow-run wordt aangemaakt met status `pending`
4. Redirect naar `/workflow/run?id={run_id}`

### Workflow uitvoeren (`/workflow/run?id={run_id}`)

**Layout:** Verticale stepper/timeline (vergelijkbaar met het bestaande accordion-patroon)

```
┌─────────────────────────────────────────┐
│ YouTube Extractor                    [x]│
│ Project: Mijn Project                   │
├─────────────────────────────────────────┤
│                                         │
│ ● Stap 1: Transcript ophalen    ✓      │
│   [Ingeklapt: output preview]           │
│                                         │
│ ● Stap 2: Opslaan als ScratchPad ✓     │
│   [Ingeklapt: "Opgeslagen als: ..."]    │
│                                         │
│ ◉ Stap 3: Vertalen of samenvatten 🔄   │  ← huidige stap
│   ┌─────────────────────────────────┐   │
│   │ [Output van stap 1 - bewerkbaar]│   │
│   │                                 │   │
│   │ Actie: [Vertalen ▼]            │   │
│   │ Taal:  [Nederlands ▼]          │   │
│   │                                 │   │
│   │ [Doorgaan]  [Overslaan]  [Stop] │   │
│   └─────────────────────────────────┘   │
│                                         │
│ ○ Stap 4: Resultaat opslaan    ⏳      │
│   [Nog niet uitgevoerd]                 │
│                                         │
├─────────────────────────────────────────┤
│ [Annuleer workflow]                     │
└─────────────────────────────────────────┘
```

**Stapstatusindicatoren:**
- `○` Pending (grijs)
- `◉` Actief / wacht op invoer (blauw)
- `✓` Voltooid (groen)
- `✗` Mislukt (rood)
- `⊘` Overgeslagen (grijs doorgestreept)

**Interactie per stap:**

1. **Automatische stap** (`requires_approval = false`): Voert direct uit, toont spinner, klapt in na voltooiing
2. **Goedkeuringsstap** (`requires_approval = true`):
   - Toont output in een Quill-viewer/editor (bewerkbaar)
   - Toont stap-specifieke invoervelden (afhankelijk van step_type)
   - Knoppen: "Doorgaan" (volgende stap), "Overslaan" (skip deze stap), "Stop" (pauzeer workflow)

**AI-stappen met streaming:** Bij `ai_transform` stappen wordt Claude-output gestreamd naar de UI via SSE (hergebruik van het bestaande `actionStreamClaude`-patroon).

### Hervattend gedrag

Een gepauzeerde of mislukte workflow kan hervat worden:
1. Gebruiker navigeert naar `/workflow/run?id={run_id}`
2. Voltooide stappen zijn ingeklapt met preview
3. Huidige/mislukte stap is uitgeklapt
4. Gebruiker kan de input bewerken en opnieuw uitvoeren

---

## Bestaande infrastructuur (hergebruik)

| Component | Pad | Relevantie |
|---|---|---|
| `YouTubeTranscriptService` | `yii/services/YouTubeTranscriptService.php` | Hergebruiken voor `youtube_transcript` stap |
| `ClaudeCliService` | `yii/services/ClaudeCliService.php` | Hergebruiken voor `ai_transform` stap (execute + streaming) |
| `ClaudeWorkspaceService` | `yii/services/ClaudeWorkspaceService.php` | Workspace-beheer voor AI-stappen in projectcontext |
| `PromptGenerationService` | `yii/services/PromptGenerationService.php` | Hergebruiken voor `prompt_generate` stap |
| `CopyFormatConverter` | `yii/services/CopyFormatConverter.php` | Delta → markdown conversie voor AI-input |
| `EntityPermissionService` | `yii/services/EntityPermissionService.php` | RBAC-patroon volgen voor nieuwe entiteiten |
| `ScratchPadController::actionStreamClaude` | `yii/controllers/ScratchPadController.php:454` | SSE-streaming patroon hergebruiken |
| `ProjectContext` | `yii/components/ProjectContext.php` | Project-scoping voor workflow-runs |
| `ProjectUrlManager` | `yii/components/ProjectUrlManager.php` | URL-prefix `workflow` toevoegen aan project-scoped routes |
| `TimestampTrait` | `yii/models/traits/TimestampTrait.php` | Timestamps voor nieuwe modellen |
| `QuillViewerWidget` | `yii/widgets/QuillViewerWidget.php` | Delta-weergave in stap-resultaten |
| `ContentViewerWidget` | `yii/widgets/ContentViewerWidget.php` | Copy-to-clipboard voor stapresultaten |
| Accordion-patroon | `yii/views/prompt-instance/_form.php` | Stepper-UI inspiratie |
| `PromptInstanceForm` | `yii/models/PromptInstanceForm.php` | Form-model patroon voor workflow-invoer |
| `YouTubeImportForm` | `yii/models/YouTubeImportForm.php` | Validatiepatroon voor URL-invoer |
| `ScratchPadController::actionSave` | `yii/controllers/ScratchPadController.php` | Patroon voor AJAX ScratchPad-aanmaak |

---

## Toegangscontrole

### RBAC

| Entiteit | Regel | Controle |
|---|---|---|
| WorkflowRecipe | `WorkflowRecipeOwnerRule` | `user_id` van recipe moet overeenkomen met ingelogde gebruiker. Systeemrecepten (`is_system = true`) zijn leesbaar voor alle geauthenticeerde gebruikers. |
| WorkflowRun | `WorkflowRunOwnerRule` | `user_id` van run moet overeenkomen met ingelogde gebruiker. |
| WorkflowStepResult | Via WorkflowRun | Geen eigen RBAC-regel nodig; toegang via parent run. |

### Permissies

| Actie | Permissie | Regel |
|---|---|---|
| Recepten bekijken | `viewWorkflowRecipe` | Eigen of systeem (`is_system`) |
| Recept aanmaken | `createWorkflowRecipe` | Toekomstig — eerste versie alleen systeemrecepten |
| Workflow starten | `createWorkflowRun` | Geauthenticeerde gebruiker |
| Run bekijken/hervatten | `viewWorkflowRun` | `WorkflowRunOwnerRule` |
| Run annuleren | `updateWorkflowRun` | `WorkflowRunOwnerRule` |
| Run verwijderen | `deleteWorkflowRun` | `WorkflowRunOwnerRule` |

### Controller behaviors

Volg het bestaande patroon uit `ScratchPadController::behaviors()`:
- Niet-model acties (index, create/start): alleen `@` authenticatie
- Model-gebonden acties (view, update, delete, execute-step, stream-step): eigenaarschapscontrole via `findModel()`

---

## Edge cases en foutafhandeling

| Scenario | Gedrag |
|---|---|
| YouTube transcript niet beschikbaar | Stap faalt, foutmelding tonen, gebruiker kan URL aanpassen en opnieuw proberen |
| Claude CLI timeout (>3600s) | Stap faalt met timeout-melding, gebruiker kan opnieuw proberen |
| Claude CLI fout (exit code ≠ 0) | Stap faalt, foutmelding opslaan in `WorkflowStepResult.error_message`, tonen in UI |
| URL-fetch mislukt (404, timeout, SSL) | Stap faalt met specifieke foutmelding, gebruiker kan URL aanpassen |
| URL-fetch levert geen bruikbare content | Stap voltooid maar met waarschuwing; gebruiker kan in goedkeuringsstap beoordelen |
| Gebruiker sluit browser tijdens workflow | Run blijft in status `running` of `paused`. Bij terugkeer toont UI de huidige status. Eventuele lopende processen (Claude CLI) draaien door tot timeout. |
| Project verwijderd terwijl workflow loopt | Run wordt `failed` met melding "Project niet meer beschikbaar" |
| ScratchPad opslaan mislukt (validatie) | Stap faalt, fout tonen, gebruiker kan naam/project aanpassen en opnieuw proberen |
| Workflow run hervatten na fout | Mislukte stap is bewerkbaar. Gebruiker past input aan en klikt "Opnieuw proberen". Eerdere voltooide stappen blijven intact. |
| Twee browser-tabs met dezelfde run | Tweede tab toont huidige status. Alleen de tab die actief een stap uitvoert kan wijzigingen maken (optimistic locking via `updated_at`). |
| Lege output van AI-stap | Stap voltooid met waarschuwing "AI heeft geen output geproduceerd". Gebruiker kan handmatig invoer toevoegen of opnieuw proberen. |
| Stap overslaan | Status wordt `skipped`. Volgende stap die `content_source: "step:N"` referendeert naar een overgeslagen stap krijgt lege input — tonen als waarschuwing. |

---

## Niet in scope (eerste versie)

| Onderwerp | Reden |
|---|---|
| **Door gebruiker aanmaakbare recepten** | Architectuur houdt hier rekening mee (WorkflowRecipe + WorkflowStep zijn generiek), maar de UI voor recept-creatie komt in een latere versie. Eerste versie levert alleen systeemrecepten via seeder/migration. |
| **Schrijven naar externe websites** | Vereist authenticatie-integratie met CMS/hosting. Het `url_fetch` staptype is read-only. Schrijven is een toekomstig staptype. |
| **Parallelle stappen** | Stappen worden sequentieel uitgevoerd. Parallelle uitvoering (bijv. dezelfde content door 3 rollen tegelijk) is een toekomstige optimalisatie. |
| **Achtergronduitvoering (queue)** | Geen job-queue infrastructuur aanwezig. Workflows draaien in de HTTP-request/SSE-context. |
| **Workflow templates delen** | Recepten zijn per gebruiker of systeem. Delen van custom recepten tussen gebruikers komt later. |
| **Branching/conditionele logica** | Stappen zijn lineair. Conditionele flows (als X dan stap A, anders stap B) komen later. |
| **Webhook/API triggers** | Workflows worden alleen handmatig gestart via de UI. |
| **Versiebeheer van recepten** | Systeemrecepten worden via migrations/seeders bijgewerkt. Geen versiebeheer-UI. |
| **Recept-specifieke CLAUDE.md** | AI-stappen gebruiken de project-workspace. Recept-specifieke systeem-prompts komen later. |

---

## Toekomstbestendigheid

De architectuur is ontworpen met het oog op de volgende toekomstige uitbreidingen:

1. **Nieuwe staptypes:** Het enum-gebaseerde `step_type` systeem is uitbreidbaar. Nieuwe types (bijv. `email_send`, `cms_write`, `api_call`) vereisen alleen een nieuwe step-handler, geen schemawijzigingen.

2. **Door gebruiker configureerbare recepten:** `WorkflowRecipe` en `WorkflowStep` zijn al data-gedreven. Een toekomstige UI kan dezelfde tabellen gebruiken om recepten samen te stellen.

3. **Conditionele logica:** `WorkflowStep.config` kan uitgebreid worden met `conditions` zonder schemawijziging.

4. **Parallelle stappen:** Een toekomstig `parallel_group` veld in `WorkflowStep` kan stappen groeperen voor parallelle uitvoering.

5. **Website schrijven:** Een nieuw staptype `cms_write` kan worden toegevoegd zodra er een CMS-integratie beschikbaar is.

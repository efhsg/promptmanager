# Reviews

---
# RONDE 2
---

## Review: Architect (ronde 2) — 2026-02-18

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Spec is na ronde 1 architectureel solide — Open/Closed, interface-based capabilities, geen onnodige abstracties
- Implementatievolgorde met dependencies is helder en correct
- Genamespaced `ai_options` zonder migratie is pragmatisch en backward-compatible
- B-1 refactor is correct geïdentificeerd als prerequisite voor FR-7

### Verbeterd
- **`ProjectController` DI injection expliciet gemaakt**: Toegevoegd dat constructor wijzigt van `AiProviderInterface $aiProvider` naar `AiProviderRegistry $providerRegistry`. Was impliciet, nu een expliciet acceptatiecriterium in FR-3.
- **`actionAiCommands()` provider-awareness**: Toegevoegd dat `actionAiCommands()` een optionele `provider` parameter accepteert om commands per provider te laden. Was niet gedekt — elke provider kan eigen commands hebben.
- **`afterDelete()` vs `actionDelete()` verduidelijkt**: `afterDelete()` op het model handelt workspace cleanup af (niet de controller). `actionDelete()` roept alleen `$model->delete()` aan. Voorkomt dubbele cleanup of race conditions.
- **`AiProviderInterface::class` definition behouden**: Expliciet in config snippet dat de bestaande `definitions` binding behouden blijft voor backward-compatibility met model hooks (`afterSave`, `afterDelete`).

### Nog open
- Geen

---

## Review: Security (ronde 2) — 2026-02-18

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- RBAC owner rules ongewijzigd — geen nieuwe access control surface
- `escapeshellarg()` eis is expliciet per provider als acceptatiecriterium (FR-4, FR-7)
- Provider key validatie in `loadAiOptions()` via `AiProviderRegistry::has()` — solide
- Whitelist-per-provider (via `buildCommand()`) is veiliger dan centrale whitelist — provider kent eigen keys
- Cross-provider session reset voorkomt session hijacking tussen providers
- Credential mounts gedocumenteerd met read-only waar mogelijk

### Verbeterd
- **XSS-preventie bij dynamische configSchema rendering**: Toegevoegd als acceptatiecriterium bij FR-5. `configSchema` labels, hints en option-teksten worden ge-escaped via `Html::encode()` (server-side) of DOM `textContent` (client-side). Voorkomt XSS als een provider kwaadwillende strings in schema-data plaatst.
- **`actionAiCommands()` provider parameter validatie**: Toegevoegd dat de nieuw toegevoegde `provider` parameter gevalideerd wordt tegen `AiProviderRegistry::has()` met fallback naar default. Voorkomt dat onbekende provider identifiers worden verwerkt.

### Nog open
- Geen

---

## Review: UX/UI Designer (ronde 2) — 2026-02-18

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Single-provider → multi-provider UX transition is correct: cards bij 1, tabs bij >1 — geen onnodige complexiteit
- Wireframes zijn concreet voor beide scenario's (single en multi-provider) met duidelijke hiërarchie
- Accessibility is volledig: ARIA roles, keyboard nav (Arrow keys), `prefers-reduced-motion`, `aria-describedby` voor hints
- Chat view wireframes tonen duidelijk het verschil tussen Claude (met permission mode) en Codex (zonder)
- Default tab behavior en tab state persistence bij validation errors zijn expliciet
- Context tab als laatste tab is logisch — provider-agnostische content hoort niet tussen provider-tabs

### Verbeterd
- Geen wijzigingen nodig — UX specificatie is compleet na ronde 1 verbeteringen

### Nog open
- Geen

---

## Review: Front-end Developer (ronde 2) — 2026-02-18

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Event router met meta/provider event scheiding is helder en implementeerbaar
- `data-option-key` pattern voor custom velden is extensible en clean
- `getOptions()` checkbox handling (`el.checked` vs `el.value`) is correct
- `renderProviderOptions()` met `innerHTML = ''` is acceptabel voor simple form elements
- `prefillFromDefaults()` per-provider uit genamespaced `projectDefaults` is consistent met bestaand patroon
- Codex event handlers (`onCodexThreadStarted`, `onCodexItemCompleted`, `onCodexTurnCompleted`) zijn concreet gespecificeerd

### Verbeterd
- **Project form PHP schema rendering concreet gemaakt**: Toegevoegd hoe `getConfigSchema()` type mapping werkt: select → `Html::dropDownList()`, text → `Html::textInput()`, textarea → `Html::textarea()`, checkbox → `Html::checkbox()`. Alle labels/hints via `Html::encode()`.
- **Command dropdown lazy-loading**: Toegevoegd dat AJAX fetch per provider tab pas bij eerste activatie plaatsvindt (lazy-load). Voorkomt onnodige API calls voor tabs die de gebruiker niet bezoekt.

### Nog open
- Geen

---

## Review: Developer (ronde 2) — 2026-02-18

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- B-1 refactor is direct implementeerbaar: streaming path delegatie + sync fallback + drie methoden verwijderen
- `resolveProvider()` geeft al de juiste provider — `parseStreamResult()` aanroep is triviale toevoeging
- Constructor injection consistent: `ProjectController` → `AiProviderRegistry`, `AiChatController` al registry-aware
- `isNamespacedOptions()` heuristic is robuust: `_default` key matcht niet op provider identifier pattern (begint met `_`)
- Codex CLI command structuur concreet met exacte flags
- Implementatievolgorde logisch met correct geïdentificeerde dependencies

### Verbeterd
- **`setAiOptionsForProvider()` empty value filtering**: Toegevoegd dat lege values gefilterd worden (consistent met bestaand `setAiOptions()` gedrag). Was al een test scenario maar miste als expliciet acceptatiecriterium.
- **`getAiCommandBlacklist()` en `getAiCommandGroups()` signature verduidelijkt**: Optionele `?string $provider = null` parameter. Callers zonder parameter behouden bestaand gedrag via `getDefaultProvider()`. Maakt de methoden testbaar en expliciet provider-aware.

### Nog open
- Geen

---

## Review: Tester (ronde 2) — 2026-02-18

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Test scenarios zijn concreet met input/output — direct implementeerbaar als Codeception tests
- Edge case tests mappen 1-op-1 naar edge cases tabel
- Test naming volgt `test{Action}When{Scenario}` conventie
- Regressie-impact sectie identificeert concreet welke bestaande tests moeten wijzigen
- Mock pattern (constructor injection) wordt gerespecteerd

### Verbeterd
- **Ontbrekende tests voor ronde 2 wijzigingen toegevoegd**:
  - `testActionAiCommandsWithProviderParameter` — valideert de nieuw toegevoegde provider parameter op `actionAiCommands()`
  - `testActionAiCommandsWithUnknownProviderFallsBack` — valideert fallback gedrag bij onbekende provider
  - `testGetAiCommandBlacklistWithProviderParameter` — valideert provider-aware reading uit genamespaced opties
  - `testGetAiCommandGroupsDefaultsToDefaultProvider` — valideert backward-compat zonder parameter
  - `testAfterDeleteIteratesAllWorkspaceProviders` — valideert dat `afterDelete()` alle workspace providers aanroept
- **Regressie-impact uitgebreid**: `ProjectControllerTest` toegevoegd — constructor injection wijzigt, `actionAiCommands()` heeft nieuwe parameter.

### Nog open
- Geen

---

## Review: Architect — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Plugin-architectuur volgt Open/Closed principe: nieuwe providers vereisen geen wijzigingen aan bestaande controllers, views, of job
- Interface-gebaseerde design met optionele capabilities (`AiConfigProviderInterface`, `AiStreamingProviderInterface`, `AiWorkspaceProviderInterface`) is elegant en minimaal
- Genamespaced `ai_options` zonder database migratie is pragmatisch — backward-compatible met automatische legacy detectie
- `RunAiJob` refactor (B-1) elimineert terecht de dubbele parsing logica
- Implementatievolgorde is correct met duidelijke prerequisites

### Verbeterd
- **`Project::getDefaultProvider()` fallback**: Oorspronkelijk verwees naar registry default, maar model mag niet afhankelijk zijn van services. Gewijzigd naar hardcoded `'claude'` fallback + `_default` key in JSON.
- **`Project::afterSave()` workspace sync**: Verduidelijkt dat `AiProviderRegistry` via `Yii::$container` wordt geresolved (bestaand patroon), en itereert alle workspace providers.
- **`ProjectController::loadAiOptions()`**: Toegevoegd hoe deze methode wijzigt — iteratie over provider keys in POST data, `setAiOptionsForProvider()` per provider.
- **`ProjectController::actionCreate()`**: Toegevoegd dat ook `actionCreate()` de gewijzigde `loadAiOptions()` gebruikt.
- **`projectConfigStatus` in `actionUpdate()`**: Toegevoegd dat config check provider-aware wordt.
- **`getAiCommandBlacklist()` en `getAiCommandGroups()`**: Toegevoegd dat deze provider-aware moeten worden (lezen uit genamespaced structuur).
- **`actionDelete()` workspace cleanup**: Verduidelijkt dat alle workspace providers worden aangeroepen, niet alleen de default.

### Nog open
- Geen

---

## Review: Security — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Bestaande RBAC owner rules (`ProjectOwnerRule`, `AiRunOwnerRule`) worden niet omzeild — alle provider-specifieke operaties gaan via dezelfde owner-scoped controllers
- `prepareRunRequest()` valideert provider identifier tegen registry (`has()` check) — voorkomt onbekende providers
- `buildCommand()` in `ClaudeCliProvider` gebruikt consequent `escapeshellarg()` — command injection preventie is geborgd
- Provider-specifieke opties worden opaque doorgegeven — geen interpretatie buiten de provider zelf
- Credential bestanden blijven in Docker volumes, niet in codebase of logs

### Verbeterd
- **`loadAiOptions()` provider key validatie**: Toegevoegd dat POST data keys gevalideerd worden tegen `AiProviderRegistry::has()`. Voorkomt dat een kwaadwillende gebruiker arbitraire keys in `ai_options` JSON kan injecteren.
- **`buildCommand()` `escapeshellarg()` eis expliciet**: Toegevoegd als acceptatiecriterium bij FR-4 en FR-7. Elke provider MOET `escapeshellarg()` gebruiken voor user-controlled values.
- **`allowedKeys` verwijdering verduidelijkt**: De whitelist verplaatst van controller naar provider — `buildCommand()` fungeert als de nieuwe whitelist (vertaalt alleen bekende keys). Dit is veiliger dan een centrale whitelist die niet weet welke keys bij welke provider horen.
- **Docker credential mount**: Toegevoegd dat mount read-only moet zijn waar mogelijk, en gedocumenteerd in `.env.example`.

### Nog open
- Geen

---

## Review: UX/UI Designer — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Consistente UX: bij 1 provider behoud je huidige collapsible card UX, bij >1 switch naar tabs — geen onnodige UI-complexiteit bij simpele setups
- UI states zijn compleet gespecificeerd (loading, empty, error, success, single provider)
- Hergebruik van bestaande Bootstrap 5 tabs en Alpine.js patterns
- Provider dropdown in chat view is al aanwezig en conditioneel — minimale UI-wijziging
- Wireframes zijn concreet en tonen duidelijk de hiërarchie

### Verbeterd
- **Default actieve tab**: Toegevoegd dat de default provider tab actief is bij laden, en dat tab state persistent is bij form validation errors.
- **Tab volgorde**: Verduidelijkt dat registratievolgorde in registry wordt gevolgd, Context tab altijd laatst.
- **Enkele provider wireframe**: Uitgewerkt met concrete ASCII wireframe i.p.v. alleen tekst. Card header wordt dynamisch (`"{provider_name} CLI Defaults"`).
- **Motion sensitivity**: Toegevoegd dat fade-transition bij provider switch `prefers-reduced-motion` respecteert.
- **Keyboard navigatie**: Verduidelijkt dat Arrow keys (niet Tab) navigeert tussen tabs (Bootstrap 5 standaard).

### Nog open
- Geen

---

## Review: Front-end Developer — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Event router pattern past goed bij de bestaande `onStreamEvent()` dispatch structuur — minimale verstoring
- `repopulateSelect()` hergebruik is correct — bestaand pattern voor dynamische dropdowns
- `data-option-key` attribuut op custom velden maakt `getOptions()` extensible zonder hardcoded keys
- Provider-agnostische meta events (waiting, keepalive, run_status) correct gescheiden van provider events
- Alpine.js state management hergebruik voor projectDefaults is consistent met bestaande code

### Verbeterd
- **Event router specifiek gemaakt**: Oorspronkelijk te abstract. Nu concreet: meta events (waiting, keepalive, prompt_markdown, run_status, server_error, sync_result) worden VOOR provider dispatch afgehandeld. Provider handlers ontvangen alleen provider-specifieke events.
- **`getOptions()` uitbreiding**: Toegevoegd hoe custom velden uit `#provider-custom-fields` container worden uitgelezen via `data-option-key` attributen.
- **`prefillFromDefaults()` per-provider**: Verduidelijkt dat defaults per provider uit genamespaced `projectDefaults` worden gelezen.
- **Codex event handlers concreet**: `onCodexThreadStarted()`, `onCodexItemCompleted()`, `onCodexTurnCompleted()` met verwachte data structuur.

### Nog open
- Geen

---

## Review: Developer — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- B-1 refactor is concreet met duidelijke stappen: streaming path (regel 148, 156-157 vervangen), sync path (regels 114-141 vervangen), drie methoden verwijderen. Implementeerbaar zonder ambiguïteit.
- `resolveProvider()` in `RunAiJob` (regel 58) geeft al de juiste provider — `parseStreamResult()` aanroep is triviale toevoeging
- `setAiOptionsForProvider()` is eenvoudig te implementeren: decode JSON, set namespace key, re-encode. Bestaand `setAiOptions()` pattern volgen.
- Codex CLI command structuur is concreet gedocumenteerd met exacte flags
- Constructor injection pattern consistent gevolgd — geen `Yii::$app` in services
- Implementatievolgorde is logisch met prerequisites correct geïdentificeerd

### Verbeterd
- **`isNamespacedOptions()` heuristic verduidelijkt**: Toegevoegd dat flat options altijd scalar values hebben, namespaced options minimaal 1 array value. Met key-format validatie (lowercase alphanum+dash) als extra check.

### Nog open
- Geen

---

## Review: Tester — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locatie
- [x] UI states zijn gespecificeerd (loading, error, empty, success)
- [x] Security validaties zijn expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten zijn geïdentificeerd met locatie

### Goed
- Test scenarios zijn concreet met specifieke input en verwacht resultaat — direct implementeerbaar als Codeception unit tests
- Edge case tests mappen 1-op-1 naar de edge cases tabel — goede dekking
- Test naming volgt `test{Action}When{Scenario}` conventie uit testing rules
- Codeception mock pattern (constructor injection) wordt gerespecteerd
- Unit tests en edge case tests zijn gescheiden in categorieën

### Verbeterd
- **`testGetDefaultProviderFallsBackToRegistryDefault` gecorrigeerd**: Test verwachtte "registry default" maar `getDefaultProvider()` valt terug op hardcoded `'claude'`. Hernoemd naar `testGetDefaultProviderFallsBackToHardcodedDefault`.
- **Ontbrekende tests toegevoegd**:
  - `testSetAiOptionsForProviderRemovesEmptyValues` — valideert dat lege values gefilterd worden (consistent met bestaand `setAiOptions()` gedrag)
  - `testLoadAiOptionsIgnoresUnknownProviderKeys` — valideert de security requirement uit Security review
  - `testBuildProviderDataIncludesConfigSchema` — valideert FR-5 requirement
  - `testCodexParseStreamResultHandlesEmptyStream` — null/empty input boundary test
- **Regressie-impact sectie toegevoegd**: Identificeert 4 bestaande test files die mogelijk aangepast moeten worden door B-1 refactor, workspace path wijziging, en whitelist verwijdering.

### Nog open
- Geen

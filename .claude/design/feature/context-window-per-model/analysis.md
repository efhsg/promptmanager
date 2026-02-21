### 1) Root-cause bevestiging (met code-referenties)

1. **Statische maxContext fallback in frontend**
- `yii/views/ai-chat/index.php:581` zet `maxContext: 200000`.
- `yii/views/ai-chat/index.php:3083` reset opnieuw naar `200000` bij nieuwe sessie.
- `yii/views/ai-chat/index.php:2505` gebruikt opnieuw fallback `this.maxContext || 200000` in meta-weergave.

2. **`maxContext` wordt alleen dynamisch bijgewerkt vanuit Claude `modelUsage.contextWindow`**
- `yii/views/ai-chat/index.php:1621-1628` update `this.maxContext` alleen als `data.modelUsage[model].contextWindow` aanwezig is (Claude-pad).
- Codex-pad (`onCodexTurnCompleted`) zet alleen token usage, geen context window:
  - `yii/views/ai-chat/index.php:1675-1679`.

3. **Provider-data naar view bevat geen context-window map**
- `AiChatController::buildProviderData()` geeft `models`, `permissionModes`, `configSchema`, etc., maar geen context windows:
  - `yii/controllers/AiChatController.php:1211-1238`.

4. **Codex provider levert geen model/contextWindow metadata**
- `CodexCliProvider::parseStreamResult()` vult alleen usage met `input_tokens`, `output_tokens`, `total_tokens`:
  - `yii/services/ai/providers/CodexCliProvider.php:368-374`.
- Geen `modelUsage`, geen `context_window`.

5. **Codex modelconfig bevat alleen labels**
- `yii/config/params.php:23-31` bevat modelnaam => label, maar geen context-window waarden.

6. **Extra observatie (belangrijk risico)**
- In echte Codex stream logs (`yii/storage/ai-runs/724.ndjson`, `yii/storage/ai-runs/726.ndjson`) zijn `turn.completed.usage.input_tokens` zeer hoog (tot >2M), wat wijst op **cumulatieve sessie-usage** i.p.v. “actuele context in venster”.
- Daardoor kan `% context used` ook met betere fallback nog snel 100% tonen.

---

### 2) Aanbevolen ontwerpkeuze

**Doel:** robuuste `maxContext`-resolutie op basis van provider + model, met duidelijke fallbacklagen.

**Runtime source priority (hoog → laag):**
1. **Live event metadata** (`result.modelUsage[*].contextWindow` of expliciete `context_window` indien aanwezig)
2. **Provider+model map uit backend-config** (naar view via `providerData`)
3. **Provider fallback** (bijv. `codex` fallback als model onbekend/leeg)
4. **Global fallback** (laatste redmiddel, huidige 200000)

**Fallback-map structuur (voorstel):**
- Per provider:
  - `modelContextWindows`: `{ modelId: int }`
  - `defaultContextWindow`: `int`
- Globaal:
  - `aiContext.defaultContextWindow`: `int`

Belangrijk: waarden blijven **config-gedreven** in `params.php` (niet hardcoded in JS).

---

### 3) Concrete implementatieplan (implementation-ready, nog niet uitvoeren)

1. **Config uitbreiden**
- Bestand: `yii/config/params.php`
- Voeg toe:
  - `codex.contextWindows` (model->window map)
  - `codex.defaultContextWindow`
  - evt. top-level `aiContext.defaultContextWindow`

2. **Codex provider constructor/data uitbreiden**
- Bestand: `yii/services/ai/providers/CodexCliProvider.php`
- Constructor uitbreiden met:
  - `array $modelContextWindows = []`
  - `?int $defaultContextWindow = null`
- Public getters toevoegen:
  - `getModelContextWindows(): array`
  - `getDefaultContextWindow(): ?int`
- (Geen wijziging van execute/stream gedrag nodig voor minimale scope)

3. **DI wiring aanpassen**
- Bestand: `yii/config/main.php`
- Bij `aiProvider.codex` ook context-window config injecteren uit `$params`.

4. **ProviderData contract uitbreiden**
- Bestand: `yii/controllers/AiChatController.php`
- In `buildProviderData()` opnemen:
  - `modelContextWindows`
  - `defaultContextWindow`
- Voor providers zonder data: lege map + null default (BC-safe).

5. **Frontend maxContext-resolutie centraliseren**
- Bestand: `yii/views/ai-chat/index.php`
- Toevoegen helper:
  - `resolveMaxContext({providerId, modelId, runtimeContextWindow})`
- Gebruik op:
  - init
  - provider change
  - model change (nieuwe listener op `#ai-model`)
  - stream-result updates (Claude/Codex)
  - newSession reset (niet hard terug naar 200000, maar naar resolved fallback)
- Alle losse `200000` literals vervangen door resolver-uitkomst.

6. **(Optioneel maar aanbevolen) Codex stream usage robuuster**
- In `onCodexTurnCompleted` ook `cached_input_tokens` meenemen naar `streamMeta.cache_tokens` (huidig ontbreekt).
- Dit corrigeert usage-consistentie met Claude-pad.

---

### 4) Testplan

1. **Codex provider unit tests**
- Bestand: `yii/tests/unit/services/ai/providers/CodexCliProviderTest.php`
- Toevoegen:
  - constructor/getter tests voor `modelContextWindows` en `defaultContextWindow`
  - backward compat: zonder nieuwe params blijft gedrag gelijk

2. **Controller unit tests (`buildProviderData` contract)**
- Bestand: `yii/tests/unit/controllers/AiChatControllerTest.php`
- Nieuwe tests voor `actionIndex()` render-data (of private method via reflection) zodat `providerData[codex]` bevat:
  - `modelContextWindows`
  - `defaultContextWindow`
- Scenario’s:
  - codex met model-map
  - provider zonder map (claude)
  - onbekend model in frontend fallbackketen (contractniveau)

3. **Frontend scenario-validatie (handmatig/acceptatie)**
- Codex + bekend model: `%` gebruikt model-window uit map
- Codex + onbekend model: `%` gebruikt provider fallback
- Claude + runtime `modelUsage.contextWindow`: runtime waarde override’t config
- Geen provider/model data: global fallback blijft werken

---

### 5) Risico’s / trade-offs + mitigaties

1. **Risico: Codex usage lijkt cumulatief per sessie**
- Gevolg: zelfs correcte maxContext kan te snel 100% worden.
- Mitigatie:
  - kortetermijn: betere maxContext fallback (dit plan)
  - middellang: contextmeter-semantiek herzien voor Codex (bijv. delta per turn of “session tokens used” label)

2. **Risico: modelnamen wijzigen**
- Gevolg: map-miss → fallback.
- Mitigatie: provider default fallback + global fallback; map onderhoud in `params.php`.

3. **Risico: contractwijziging `providerData`**
- Gevolg: potentieel frontend regressie.
- Mitigatie: additieve velden (BC-safe), bestaande keys onaangeraakt.

---

### 6) Eindadvies: kleinste veilige wijziging vs ideaal

1. **Kleinste veilige wijziging (aanbevolen nu)**
- Config-gedreven provider+model context-window fallback toevoegen via `providerData`.
- Frontend resolver introduceren en alle `200000` literals vervangen.
- Geen brede interface-refactor nodig.

2. **Ideale langetermijnoplossing**
- Gestandaardiseerd provider-contract voor context-capabilities (bijv. aparte interface).
- Codex “context used” semantiek expliciet maken (window usage vs cumulatief sessieverbruik), inclusief aangepaste UI-labels.
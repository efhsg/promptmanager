# Feature: Multi-Provider Runtime Selection

## Samenvatting

Breid de bestaande AI provider abstractielaag uit met een registry die meerdere gelijktijdig geregistreerde providers beheert, en voeg een UI-selector toe waarmee de gebruiker per request kiest welke provider (Claude, Codex, Gemini) wordt gebruikt. Het queue job systeem lost de juiste provider op uit de `AiRun.provider` kolom.

## User story

Als gebruiker wil ik vanuit een dropdown in de chat interface kiezen welke AI CLI provider (Claude, Codex, Gemini) ik wil gebruiken, zodat ik per taak het meest geschikte model kan inzetten zonder de applicatie te herconfigureren.

## Kernprincipe

De bestaande interface-hiërarchie (`AiProviderInterface` + 4 optionele interfaces) blijft ongewijzigd, met één uitbreiding: `getSupportedModels()` wordt toegevoegd aan `AiConfigProviderInterface`. Deze feature voegt een **registry-laag** toe die meerdere provider instances beheert en requests naar de juiste provider routeert. Geen migraties — de `provider` kolom bestaat al op `ai_run` als VARCHAR(50) met regex-validatie.

## Functionele requirements

### FR-1: AiProviderRegistry Service

- Beschrijving: Een nieuwe service die alle geconfigureerde provider instances bevat en ze op identifier kan resolven. Vervangt de enkele `AiProviderInterface` DI binding als primair injectiepunt voor multi-provider contexten.
- Locatie: `yii/services/ai/AiProviderRegistry.php`
- Acceptatiecriteria:
  - [ ] Service houdt een map bij van `identifier => AiProviderInterface` instances
  - [ ] `get(string $identifier): AiProviderInterface` — retourneert provider of gooit `InvalidArgumentException`
  - [ ] `has(string $identifier): bool` — controleert of provider geregistreerd is
  - [ ] `all(): array` — retourneert alle geregistreerde providers (geïndexeerd op identifier)
  - [ ] `getDefault(): AiProviderInterface` — retourneert de default provider (eerste geregistreerde)
  - [ ] `getDefaultIdentifier(): string` — retourneert de identifier van de default provider
  - [ ] Constructor accepteert `array $providers` (numeriek geïndexeerd, elk element is `AiProviderInterface`). De constructor bouwt de identifier map via `$provider->getIdentifier()`:
    ```php
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $id = $provider->getIdentifier();
            if (isset($this->providers[$id]))
                throw new InvalidArgumentException("Duplicate provider: {$id}");
            $this->providers[$id] = $provider;
        }
        if ($this->providers === [])
            throw new InvalidArgumentException("At least one provider required");
    }
    ```
  - [ ] Gooit exception bij dubbele identifiers tijdens registratie
  - [ ] Gooit exception bij lege providers array (minimaal één provider vereist)
  - [ ] `all()` retourneert providers in registratie-volgorde (insertion order); `getDefault()` is consistent met de eerste key van `all()`

### FR-2: DI Configuratie voor Meerdere Providers

- Beschrijving: De DI container registreert alle beschikbare providers en de registry. De enkele `AiProviderInterface` binding blijft behouden als convenience alias voor de default provider (backwards compatibility).
- Locatie: `yii/config/main.php`
- Acceptatiecriteria:
  - [ ] Elke provider is geregistreerd als named definition (bijv. `'aiProvider.claude' => ClaudeCliProvider::class`)
  - [ ] `AiProviderRegistry` is geregistreerd met alle provider instances geïnjecteerd
  - [ ] `AiProviderInterface` binding behouden als alias via closure: `function() { return Yii::$container->get(AiProviderRegistry::class)->getDefault(); }`
  - [ ] Een nieuwe provider toevoegen vereist enkel: class aanmaken + DI definition toevoegen + toevoegen aan registry's provider lijst
  - [ ] Concrete DI config structuur (closure i.p.v. `Instance::of()` in arrays — Yii2 resolved `Instance` objecten niet in geneste arrays tenzij `setResolveArrays(true)` is aangeroepen, wat in dit project niet het geval is):
    ```php
    'aiProvider.claude' => ClaudeCliProvider::class,
    // 'aiProvider.codex' => CodexCliProvider::class,
    AiProviderRegistry::class => function () {
        return new AiProviderRegistry([
            Yii::$container->get('aiProvider.claude'),
            // Yii::$container->get('aiProvider.codex'),
        ]);
    },
    AiProviderInterface::class => function () {
        return Yii::$container->get(AiProviderRegistry::class)->getDefault();
    },
    ```
  - [ ] `AiProviderRegistry` wordt als singleton geregistreerd via `Yii::$container->setSingleton()` om meerdere instantiaties per request te voorkomen

### FR-3: Controller Gebruikt Registry voor Run Creatie

- Beschrijving: `AiChatController` ontvangt `AiProviderRegistry` in plaats van de enkele `AiProviderInterface`. Bij het aanmaken van een run komt de provider identifier uit het request (gebruikersselectie). De controller resolvet de geselecteerde provider uit de registry.
- Locatie: `yii/controllers/AiChatController.php`
- Acceptatiecriteria:
  - [ ] Controller constructor accepteert `AiProviderRegistry` (vervangt de huidige `AiProviderInterface` parameter)
  - [ ] `prepareRunRequest()` leest `provider` key uit request body (default: registry's default identifier)
  - [ ] `createRun()` resolvet de provider uit de registry op identifier en stempelt `$run->provider`
  - [ ] `actionStream()` en `actionStartRun()` gebruiken de request-geselecteerde provider
  - [ ] `actionCancel()` (legacy stream-token-gebaseerd endpoint) — dit endpoint ontvangt alleen een `streamToken`, geen `runId`. Omdat er geen run record beschikbaar is, roept het `cancelProcess($streamToken)` aan op **alle geregistreerde providers** via `$this->providerRegistry->all()`. Process PIDs zijn uniek per OS; alleen de juiste provider zal het process daadwerkelijk cancellen, de rest retourneert `false`. Dit is een eenvoudige en correcte oplossing.
  - [ ] `actionCancelRun()` (run-gebaseerd endpoint) — resolvet de provider uit `$run->provider` kolom via de registry en roept `cancelProcess()` aan op de juiste instance
  - [ ] `actionUsage()` en `actionCheckConfig()` — gebruiken de request-geselecteerde provider of de project-default
  - [ ] `actionSummarizeSession()` — gebruikt de default provider (interne utility, niet user-selecteerbaar). De hardcoded `'model' => 'sonnet'` en `'permissionMode' => 'plan'` worden alleen meegegeven als de default provider een `AiConfigProviderInterface` is en het betreffende model ondersteunt; anders worden lege waarden meegegeven (provider default)
  - [ ] `loadAiCommands()` — wordt aangeroepen in `actionIndex()` (GET request); gebruikt altijd de default provider bij page load. Retourneert `[]` als de default provider geen `AiConfigProviderInterface` implementeert
  - [ ] Ongeldige provider identifier retourneert `['success' => false, 'error' => 'Invalid provider selection']` (HTTP 400). De identifier wordt **niet** terug-ge-echod in de response (defense-in-depth). Server-side wordt `Yii::warning("Unknown provider attempted: {$identifier}, user: {$userId}", LogCategory::AI->value)` gelogd

### FR-4: RunAiJob Resolvet Provider uit Run Record

- Beschrijving: Het queue job leest `$run->provider` en resolvet de juiste provider uit de registry. Dit is de kritieke fix: het job moet uitvoeren met de provider die geselecteerd was toen de run werd aangemaakt.
- Locatie: `yii/jobs/RunAiJob.php`
- Acceptatiecriteria:
  - [ ] `createStreamingProvider()` wordt hernoemd naar `resolveProvider(AiRun $run): AiProviderInterface` — leest `$run->provider` en resolvet uit `AiProviderRegistry` via `Yii::$container->get(AiProviderRegistry::class)` (queue jobs hebben geen constructor DI)
  - [ ] De aanroepende code in `execute()` brancht expliciet op interface type:
    ```php
    $provider = $this->resolveProvider($run);
    if ($provider instanceof AiStreamingProviderInterface) {
        // streaming path (bestaand)
        $result = $provider->executeStreaming(...);
    } else {
        // sync fallback path (nieuw)
        $result = $provider->execute(...);
        // schrijf resultaat als NDJSON naar stream bestand
    }
    ```
  - [ ] Sync fallback schrijft resultaat als NDJSON: `{"type":"result","result":"..."}\n[DONE]\n` — geen `subtype` key (inconsistent met bestaande `extractResultText()` en `extractMetadata()` parsing die deze key niet verwachten)
  - [ ] Als de provider identifier niet gevonden wordt in de registry, markeert het job de run als failed met foutmelding: "Provider '{id}' is not configured". `[DONE]` wordt ook naar het stream bestand geschreven zodat de SSE relay niet blijft wachten
  - [ ] `buildSessionDialog()` query bevat `forUser($run->user_id)` naast `forSession()` (defense-in-depth: voorkomt theoretische data-lekkage bij gedeelde session IDs)

### FR-5: UI Provider Selector

- Beschrijving: De chat view toont een provider dropdown gevuld vanuit de registry. De geselecteerde provider stroomt mee met het request. Permission modes en model-lijsten zijn provider-specifiek en updaten dynamisch wanneer de gebruiker van provider wisselt.
- Locatie: `yii/views/ai-chat/index.php`
- Acceptatiecriteria:
  - [ ] Provider dropdown gerenderd vanuit `AiProviderRegistry::all()` (doorgegeven als view param vanuit controller `actionIndex`)
  - [ ] Default selectie: registry's default provider
  - [ ] Bij provider-wissel updaten permission modes en model dropdowns (via pre-gerenderde provider-specifieke data in JavaScript)
  - [ ] Geselecteerde provider identifier meegestuurd in request body naast prompt
  - [ ] Paginatitel gebruikt geselecteerde provider's `getName()` (bijv. "Codex CLI" i.p.v. hardcoded "Claude CLI")
  - [ ] Header (`<h1>`) reflecteert de actieve provider naam. Breadcrumbs tonen de server-side default provider naam (niet dynamisch bijgewerkt — te fragiel met Yii2 widget rendering)
  - [ ] Wanneer slechts één provider geregistreerd is, wordt de provider dropdown niet getoond

### FR-6: Provider-Specifieke Permission Modes en Models

- Beschrijving: Vervang hardcoded permission modes en model arrays met provider-gedreven data. Elke provider rapporteert zijn ondersteunde modi en modellen.
- Acceptatiecriteria:
  - [ ] Permission modes komen van `AiConfigProviderInterface::getSupportedPermissionModes()` (providers die deze interface implementeren)
  - [ ] Providers die `AiConfigProviderInterface` niet implementeren krijgen een zinvolle fallback (lege modes = "geen keuze — gebruik provider default")
  - [ ] `getSupportedModels(): array` toegevoegd aan `AiConfigProviderInterface` — retourneert `[value => label]` map van ondersteunde modellen
  - [ ] De view rendert deze dynamisch per geselecteerde provider
  - [ ] `AiPermissionMode::labels()` hergebruikt als fallback/mapping bron

### FR-7: AiCompletionClient Provider Awareness

- Beschrijving: De completion client (gebruikt voor prompt titles, session summaries, note names) gebruikt de default provider. Dit is een secundair punt — dit zijn interne utility calls waar provider-keuze minder uitmaakt.
- Acceptatiecriteria:
  - [ ] `ClaudeCliCompletionClient` blijft werken wanneer Claude de default provider is
  - [ ] De completion client is al provider-agnostisch qua implementatie (gebruikt `AiProviderInterface`), alleen de class naam verwijst naar Claude
  - [ ] Bij een andere default provider werkt de client automatisch mee via DI (de `AiProviderInterface` binding wijst naar `$registry->getDefault()`)
  - [ ] Optioneel: hernoem `ClaudeCliCompletionClient` → `AiCompletionClientImpl` of `DefaultCompletionClient` voor consistentie (low priority)

## Gebruikersflow

1. Gebruiker opent de AI Chat pagina voor een project
2. De pagina toont een provider dropdown met alle beschikbare providers (bijv. "Claude", "Codex", "Gemini")
3. De model- en permission mode dropdowns tonen opties specifiek voor de geselecteerde provider
4. Gebruiker kiest een provider, typt een prompt, en klikt Send
5. Het request bevat de geselecteerde provider identifier
6. De controller resolvet de provider uit de registry, maakt een `AiRun` aan met de juiste `provider` waarde
7. De `AiRun` wordt in de queue geplaatst
8. `RunAiJob` pikt de run op, leest `$run->provider`, resolvet de juiste provider uit de registry
9. De provider voert de prompt uit (streaming of sync), resultaat wordt teruggestuurd
10. Bij een follow-up vraag in dezelfde sessie, blijft dezelfde provider geselecteerd
11. Gebruiker kan mid-sessie wisselen van provider (nieuwe sessie start automatisch)

## Edge cases

| Case | Gedrag |
|------|--------|
| Request bevat onbekende provider | Controller retourneert error: `"Invalid provider selection"` (HTTP 400). Server-side logging met user context. Run wordt niet aangemaakt. |
| Provider geregistreerd in DI maar CLI binary ontbreekt | Run wordt aangemaakt, job voert uit, provider's `execute()` faalt, run gemarkeerd als `FAILED` met binary-not-found error. |
| Provider ondersteunt geen streaming | `RunAiJob` valt terug op sync `execute()`. Schrijft een enkel NDJSON result event + `[DONE]` naar het stream bestand. SSE relay werkt onveranderd. |
| Provider ondersteunt geen usage tracking | `actionUsage()` retourneert `"Provider does not support usage tracking"` (bestaand gedrag via `instanceof` check). |
| Provider ondersteunt geen config checking | `actionCheckConfig()` retourneert `"Provider does not support config checking"` (bestaand gedrag). |
| Cancel via stream token (`actionCancel`) | Endpoint ontvangt alleen `streamToken`, geen `runId`. Roept `cancelProcess($streamToken)` aan op **alle** providers via `$registry->all()`. Alleen de juiste provider zal het process daadwerkelijk cancellen (PIDs zijn uniek). |
| Cancel via run ID (`actionCancelRun`) | Resolvet provider uit `$run->provider` kolom, roept `cancelProcess()` aan op de juiste instance. |
| Legacy requests zonder `provider` in body | Default provider wordt gebruikt (backwards compatible). |
| Queue job voor run waarvan provider verwijderd is uit config | Job markeert run als `FAILED`: "Provider '{id}' is not configured". |
| Meerdere providers delen dezelfde identifier | Registry gooit exception tijdens constructie. Dit is een configuratiefout die bij boot wordt gevangen. |
| Slechts één provider geregistreerd | Provider dropdown wordt niet getoond; gedrag identiek aan huidige situatie. |
| Provider wissel mid-sessie | Frontend stuurt het request met nieuwe provider + sessionId. Controller query't de eerste run van de sessie (`AiRun::find()->forUser($userId)->forSession($sessionId)->orderedByCreatedAsc()->one()`) en vergelijkt `$firstRun->provider` met de geselecteerde provider. Bij mismatch: sessionId wordt genegeerd (= nieuwe sessie). |
| Cross-provider session resume | Sessie ID's zijn provider-specifiek. Een Claude session_id doorgeven aan Codex is zinloos — controller valideert dat de session provider matcht met de geselecteerde provider via bovenstaande check. |
| Project heeft `ai_options` met Claude-specifieke keys, gebruiker selecteert Codex | Provider implementatie moet onbekende option keys negeren (provider verantwoordelijkheid). |
| Provider toegevoegd na run-creatie | Run was gevalideerd met oude registry; job resolvet met nieuwe registry (na server herstart). Provider bestaat nu wel — job resolvet correct. Geen extra actie nodig. |
| Historische chat met verwijderde provider | View toont de raw provider identifier als label wanneer de provider niet meer in `providerData` staat. Visuele hint: grijze badge, cursief. Voorkomt JS runtime error bij `providerData[run.provider] === undefined`. |

## Entiteiten en relaties

### Bestaande entiteiten
- `AiRun` (`yii/models/AiRun.php`) — `provider` kolom al aanwezig (VARCHAR(50), regex-gevalideerd, default 'claude')
- `Project` (`yii/models/Project.php`) — `ai_options` JSON kolom met provider-specifieke instellingen
- `AiPermissionMode` (`yii/common/enums/AiPermissionMode.php`) — enum met permission mode waarden en labels

### Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| `AiProviderRegistry` | Service | `yii/services/ai/AiProviderRegistry.php` | Nieuw: registry die meerdere providers beheert |
| `AiConfigProviderInterface` | Interface | `yii/services/ai/AiConfigProviderInterface.php` | Wijzigen: `getSupportedModels()` methode toevoegen |
| `ClaudeCliProvider` | Provider | `yii/services/ai/providers/ClaudeCliProvider.php` | Wijzigen: `getSupportedModels()` implementeren |
| `AiChatController` | Controller | `yii/controllers/AiChatController.php` | Wijzigen: registry injecteren, provider selectie uit request lezen |
| `RunAiJob` | Job | `yii/jobs/RunAiJob.php` | Wijzigen: provider resolven uit run record via registry |
| `main.php` | Config | `yii/config/main.php` | Wijzigen: registry + meerdere providers registreren |
| `index.php` | View | `yii/views/ai-chat/index.php` | Wijzigen: provider dropdown, dynamische models/modes, dynamische titel |

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| `AiPermissionMode::labels()` | `yii/common/enums/AiPermissionMode.php` | Permission mode labels voor dropdowns; provider kan subset selecteren |
| `Html::dropDownList` | Yii2 framework | Bestaand patroon voor model/permission mode dropdowns; hergebruiken voor provider dropdown |
| `AiProviderInterface::getIdentifier()` | `yii/services/ai/AiProviderInterface.php` | Identifier matching met `AiRun.provider` kolom |
| `AiRun.provider` column validatie | `yii/models/AiRun.php` regel 89-91 | Regex `/^[a-z][a-z0-9-]{1,48}$/` al beschikbaar voor validatie |
| `instanceof` check patroon | `yii/controllers/AiChatController.php` | Bestaand patroon voor optionele interface detectie |
| `AiCompletionClient` interface | `yii/services/AiCompletionClient.php` | Al abstract; geen wijziging nodig |

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| Registry als service, niet als factory | Providers worden bij boot geïnstantieerd en hergebruikt. Geen lazy loading nodig — providers zijn lichtgewicht (geen connecties bij constructie). |
| `getSupportedModels()` toevoegen aan `AiConfigProviderInterface` | Past bij bestaand patroon: `getSupportedPermissionModes()` zit al op dit interface. Minimale interface-uitbreiding, backwards compatible (nieuwe methode). |
| Provider selectie per-request, niet per-project | Flexibiliteit: gebruiker wisselt vrij. Project-level defaults zijn toekomstige uitbreiding. |
| Enkele `AiProviderInterface` binding behouden | Backwards compatibility voor code die direct `AiProviderInterface` type-hint (bijv. `ClaudeCliCompletionClient`). |
| Provider-data pre-renderen als JS object | Vermijdt AJAX call bij provider wissel. Alle providers, modes en models worden als JSON object in de view geladen. Schaalbaar tot ~10 providers. |
| Sync fallback in RunAiJob | Niet alle providers ondersteunen streaming. Sync fallback schrijft result als NDJSON zodat de bestaande SSE relay ongewijzigd blijft. |

## Open vragen

Geen.

## UI/UX overwegingen

### Layout/Wireframe

```
┌──────────────────────────────────────────────────────────┐
│ Header                                                    │
│   <h1>🖥 {Provider Name} CLI</h1>                        │
├──────────────────────────────────────────────────────────┤
│ Combined Bar (collapsed settings + usage)                 │
│ ┌─────────────────────────────────────┐ ┌──────────────┐ │
│ │ [Claude ▼] Project | Branch | Config│ │ Usage bars   │ │
│ └─────────────────────────────────────┘ └──────────────┘ │
├──────────────────────────────────────────────────────────┤
│ Settings Card (expanded on first visit)                   │
│ ┌────────────┐ ┌──────────────┐ ┌──────────────────────┐ │
│ │ Provider ▼ │ │ Model ▼      │ │ Permission Mode ▼    │ │
│ │ [Claude  ▼]│ │ (provider-   │ │ (provider-specifiek) │ │
│ │ <select>   │ │  specifiek)  │ │ <select>             │ │
│ │            │ │ <select>     │ │                      │ │
│ └────────────┘ └──────────────┘ └──────────────────────┘ │
├──────────────────────────────────────────────────────────┤
│ Chat area                                                 │
│ ┌──────────────────────────────────────────────────────┐ │
│ │ [User bubble]                                        │ │
│ │ Prompt tekst...                                      │ │
│ ├──────────────────────────────────────────────────────┤ │
│ │ [Claude response]  ← label = provider naam           │ │
│ │ Antwoord tekst...                                    │ │
│ └──────────────────────────────────────────────────────┘ │
├──────────────────────────────────────────────────────────┤
│ Prompt editor (ongewijzigd)                               │
└──────────────────────────────────────────────────────────┘
```

**Plaatsing provider dropdown:**
- In de **settings card** als eerste kolom (vóór Model en Permission Mode) — interactief `<select>` dropdown via `Html::dropDownList('provider', $default, $providers, ['id' => 'ai-provider', 'class' => 'form-select', 'aria-label' => 'Select AI provider'])`
- In de **combined bar** als **read-only badge** (niet interactief) — `<span class="badge bg-secondary ai-provider-badge" title="{full name}">{name}</span>`, geplaatst als eerste badge vóór het project badge. Badge heeft `max-width: 120px` en `text-overflow: ellipsis` voor lange namen (bijv. "Azure OpenAI GPT-4 Turbo"). Badge wordt geüpdatet in `updateSettingsSummary()`.
- Bij slechts één provider: geen dropdown in settings card, enkel de provider naam als label in combined bar

**Mobiel (< 768px):**
- Provider dropdown wordt full-width boven Model en Permission Mode weergegeven
- Combined bar stackt verticaal: provider badge + settings → usage

**Chat bubbles:**
- Response bubbles tonen de provider naam als label (bijv. "Claude", "Codex") i.p.v. hardcoded "Claude"
- Provider naam komt uit de `AiRun.provider` kolom → registry lookup voor display naam
- Fallback bij verwijderde provider: toon de raw identifier als label met grijze badge en cursieve tekst (bijv. `<span class="text-muted fst-italic">{identifier}</span>`)

### UI States

| State | Visueel |
|-------|---------|
| Loading | Provider dropdown disabled met placeholder "Loading..." totdat provider data geladen is (instant bij page load, data is inline) |
| Single provider | Provider dropdown niet getoond; gedrag identiek aan huidige UI |
| Multiple providers | Provider dropdown zichtbaar met alle geregistreerde providers |
| Provider switch | Model en permission mode dropdowns updaten naar provider-specifieke opties. Bestaande selecties die niet bestaan bij nieuwe provider resetten naar lege waarde "(Use default)". Geen bevestigingsdialoog — wissel is direct. |
| Provider switch mid-sessie | Indien de gebruiker een actieve sessie heeft (sessionId is set) en van provider wisselt: toon **persistente** inline waarschuwing boven prompt editor: "Switching provider will start a new session." Waarschuwing verdwijnt wanneer: (a) gebruiker terugwisselt naar de originele sessie-provider, of (b) eerste send met de nieuwe provider (sessie is dan daadwerkelijk gewisseld). Bij snel achter elkaar wisselen: vorige waarschuwing wordt vervangen (niet gestackt). |
| Error (unknown provider) | Toast/alert: "The selected provider is no longer available. Please refresh the page." — run niet aangemaakt |
| Provider zonder config support | Config badge toont "N/A" i.p.v. config status |
| Provider zonder usage support | Usage bar toont "Usage not available for {provider name}" |

### Accessibility
- Provider dropdown heeft `aria-label="Select AI provider"`
- Concreet aria-live element: `<div id="ai-provider-status" class="visually-hidden" aria-live="polite"></div>` — wordt geüpdatet bij provider wissel met "Model and permission options updated for {provider name}"
- Keyboard navigatie: Tab-volgorde Provider → Model → Permission Mode
- Alle dropdowns behouden bestaande focus-management

## Security overwegingen

| Concern | Mitigatie |
|---------|-----------|
| Provider identifier uit request body | Gevalideerd als whitelist-check tegen registry (`$registry->has()`). Alleen bekende identifiers geaccepteerd. Voldoet aan bestaande regex op `AiRun.provider`: `/^[a-z][a-z0-9-]{1,48}$/`. |
| RBAC toegangscontrole | Bestaande `behaviors()` op `AiChatController` blijven ongewijzigd. Project-eigenaarschap wordt gecontroleerd via `findProject()` die filtert op `user_id`. Geen nieuwe actions, dus geen nieuwe RBAC rules nodig. |
| Cross-provider session hijack | Session query's altijd scoped met `forUser($userId)` — een gebruiker kan geen sessies van andere gebruikers resolven. |
| Provider-data blootgesteld aan frontend | Pre-gerenderde provider data bevat enkel: identifier, naam, model-lijst, permission modes. Geen credentials, tokens of interne paden. |
| Provider credential isolatie | Elke provider beheert zijn eigen credentials. Registry deelt geen credentials tussen providers. Credentials worden niet gelogd of in responses opgenomen. |
| `actionUsage()` en `actionCheckConfig()` met provider parameter | Voor GET requests: provider identifier doorgegeven als query parameter, gevalideerd tegen registry whitelist. Bij onbekende provider: early return met foutmelding, geen provider-specifieke code uitgevoerd. |
| Error messages met provider identifier | Error responses bevatten generieke melding "Invalid provider selection" zonder de identifier terug te echo'en (defense-in-depth). Server-side logging met user context via `Yii::warning()`. In views altijd `Html::encode()` toepassen. |
| Security audit logging | Bij ongeldige provider attempts: `Yii::warning("Unknown provider attempted: {$id}, user: {$userId}", LogCategory::AI->value)`. Bij provider-mismatch bij session resume: `Yii::warning("Cross-provider session attempt: {$provider} vs {$sessionProvider}, user: {$userId}", LogCategory::AI->value)`. Consistent met `.claude/rules/security.md` logging requirements. |
| `buildSessionDialog` in RunAiJob | Session query scoped met `forUser($run->user_id)` naast `forSession()`. Defense-in-depth tegen theoretische session ID collisions tussen gebruikers. |

## Technische overwegingen

### Backend

#### Endpoints (bestaand, gewijzigd)
| Endpoint | Wijziging |
|----------|-----------|
| `POST /ai-chat/stream?p={id}` | Request body accepteert `provider` key; provider uit registry resolven |
| `POST /ai-chat/start-run?p={id}` | Idem |
| `POST /ai-chat/cancel?p={id}` | Legacy stream-token-gebaseerd: roept `cancelProcess()` aan op alle providers (streamToken-only, geen run record) |
| `POST /ai-chat/cancel-run?runId={id}` | Run-gebaseerd: resolvet provider uit `$run->provider` kolom |
| `GET /ai-chat/index?p={id}` | Provider registry data meegeven als view params |
| `GET /ai-chat/usage?p={id}` | Provider uit query param of request header resolven |
| `GET /ai-chat/check-config?p={id}` | Idem |

#### Provider data generatie voor view (in `actionIndex`)
De controller bouwt een provider data array vanuit de registry en geeft deze mee aan de view:
```php
$providerData = [];
foreach ($this->providerRegistry->all() as $id => $provider) {
    $entry = ['name' => $provider->getName(), 'identifier' => $id, 'supportsUsage' => false, 'supportsConfig' => false];
    if ($provider instanceof AiConfigProviderInterface) {
        $entry['models'] = array_merge(['' => '(Use default)'], $provider->getSupportedModels());
        $supportedModes = $provider->getSupportedPermissionModes();
        $allLabels = AiPermissionMode::labels();
        $filteredLabels = array_intersect_key($allLabels, array_flip($supportedModes));
        $entry['permissionModes'] = array_merge(['' => '(Use default)'], $filteredLabels);
        $entry['supportsConfig'] = true;
    } else {
        $entry['models'] = ['' => '(Use default)'];
        $entry['permissionModes'] = [];
    }
    $entry['supportsUsage'] = $provider instanceof AiUsageProviderInterface;
    $providerData[$id] = $entry;
}
```

#### Validatie
- Provider identifier gevalideerd als whitelist-check tegen registry (`$registry->has($identifier)`)
- Bestaande `AiRun.provider` regex validatie blijft van kracht
- Request body `provider` key is optioneel (default: registry default)

#### Services
- `AiProviderRegistry` wordt als singleton geregistreerd via `Yii::$container->setSingleton()` — één instance per request. PHP queue workers zijn separate processes; de registry wordt per-process opgebouwd en niet gedeeld (thread-safety is geen concern)
- Registry is read-only na constructie — geen runtime registratie/deregistratie

### Frontend

#### JavaScript wijzigingen (inline in `index.php`)

**Provider data object** (pre-gerenderd door PHP controller als `Json::encode()`):
```javascript
var providerData = {
    "claude": {
        "name": "Claude",
        "identifier": "claude",
        "models": {"": "(Use default)", "sonnet": "Sonnet", "opus": "Opus", "haiku": "Haiku"},
        "permissionModes": {"": "(Use default)", "plan": "Plan (restricted to planning)", ...},
        "supportsUsage": true,
        "supportsConfig": true
    },
    "codex": {
        "name": "Codex",
        "identifier": "codex",
        "models": {"": "(Use default)", "codex-mini": "Codex Mini"},
        "permissionModes": {},
        "supportsUsage": false,
        "supportsConfig": false
    }
};
var defaultProvider = "claude";
```

**Element IDs:** De bestaande IDs `claude-model` en `claude-permission-mode` worden hernoemd naar `ai-model` en `ai-permission-mode` om provider-agnostisch te zijn. Alle bestaande referenties in de JS (minstens 5 plaatsen: `getOptions()`, `prefillFromDefaults()`, `updateSettingsSummary()`, `checkConfigStatus()`) moeten worden bijgewerkt. Het nieuwe provider dropdown element krijgt `id="ai-provider"`.

**Provider switch handler** (onderdeel van `setupEventListeners()` zodat `self = this` beschikbaar is):
```javascript
// Binnen setupEventListeners():
var providerEl = document.getElementById('ai-provider');
if (providerEl) {
    providerEl.addEventListener('change', function() {
        var id = this.value;
        if (!Object.hasOwn(providerData, id)) return; // guard tegen prototype pollution
        var data = providerData[id];
        // 1. Repopuleer model dropdown
        repopulateSelect('ai-model', data.models);
        // 2. Repopuleer permission mode dropdown
        repopulateSelect('ai-permission-mode', data.permissionModes);
        // 3. Update titel (behoud app name uit Yii2 layout)
        var appSuffix = document.title.indexOf(' - ') > -1
            ? ' - ' + document.title.split(' - ').slice(1).join(' - ')
            : '';
        document.title = data.name + ' CLI' + appSuffix;
        document.querySelector('.ai-chat-page h1').textContent = data.name + ' CLI';
        // 4. Session waarschuwing (persistent, niet timer-based)
        if (self.sessionId) {
            self.showProviderSwitchWarning(data.name);
        }
        // 5. Update combined bar en settings summary
        self.updateSettingsSummary();
        // 6. Usage/config badge updaten
        self.updateCapabilityBadges(data);
        // 7. Aria-live update
        var statusEl = document.getElementById('ai-provider-status');
        if (statusEl) statusEl.textContent = 'Model and permission options updated for ' + data.name;
    });
}
```

**`showProviderSwitchWarning(providerName)`:** Toont persistente inline waarschuwing boven prompt editor. Bij herhaalde aanroep wordt de vorige waarschuwing vervangen (niet gestackt). Waarschuwing verdwijnt bij: (a) terugwisselen naar originele provider, (b) eerste send met nieuwe provider.

**`updateCapabilityBadges(data)`:** Update de config badge en usage summary op basis van `data.supportsConfig` en `data.supportsUsage`. Config badge toont "N/A" als `supportsConfig === false`. Usage bar toont "Usage not available for {name}" als `supportsUsage === false`.

**`getOptions()` uitbreiding:**
```javascript
getOptions: function() {
    var providerEl = document.getElementById('ai-provider');
    return {
        provider: providerEl ? providerEl.value : defaultProvider,
        model: document.getElementById('ai-model').value,
        permissionMode: document.getElementById('ai-permission-mode').value
    };
}
```

**Helper `repopulateSelect(id, options)`:** Verwijdert alle `<option>` elementen en bouwt nieuwe opties vanuit de provider data map. De "(Use default)" optie is onderdeel van de `options` map (key `''`).

**Breadcrumbs:** Server-side breadcrumb wordt gerenderd met de default provider naam. Client-side breadcrumb update wordt **niet** geïmplementeerd (te fragiel met Yii2 widget rendering). De breadcrumb toont altijd de initiële provider naam; dit is acceptabel omdat breadcrumbs navigatie-hulpmiddelen zijn, niet status-indicatoren.

#### Geen aparte JS modules
De huidige architectuur heeft geen aparte JS modules voor de chat view (alles inline in de PHP view). Dit patroon wordt gevolgd.

## Test scenarios

### Test file locaties

| Test class | Locatie |
|------------|---------|
| `AiProviderRegistryTest` | `yii/tests/unit/services/ai/AiProviderRegistryTest.php` |
| `RunAiJobTest` (bestaand, uitbreiden) | `yii/tests/unit/jobs/RunAiJobTest.php` |
| `ClaudeCliProviderTest` (bestaand, uitbreiden) | `yii/tests/unit/services/ai/providers/ClaudeCliProviderTest.php` |

### Unit tests

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| Registry: get existing provider | `get('claude')` | Retourneert `ClaudeCliProvider` instance |
| Registry: get non-existent provider | `get('nonexistent')` | Gooit `InvalidArgumentException` met message "Unknown provider: nonexistent" |
| Registry: has existing provider | `has('claude')` | Retourneert `true` |
| Registry: has non-existent provider | `has('nonexistent')` | Retourneert `false` |
| Registry: all providers | `all()` | Retourneert array geïndexeerd op identifier met alle providers, in registratie-volgorde |
| Registry: all preserves insertion order | Constructor met providers alpha, beta, gamma | `array_keys($registry->all())` === `['alpha', 'beta', 'gamma']` |
| Registry: default provider | `getDefault()` | Retourneert eerste geregistreerde provider |
| Registry: default identifier | `getDefaultIdentifier()` | Retourneert identifier van eerste provider |
| Registry: duplicate identifier | Constructor met 2 providers die dezelfde identifier retourneren | Gooit `InvalidArgumentException` met message over duplicate |
| Registry: empty providers | Constructor met `[]` | Gooit `InvalidArgumentException` met message "at least one provider" |
| Registry: multiple providers | Constructor met 3 mock providers | `all()` retourneert 3 entries; `get()` resolvet elk correct |
| ClaudeCliProvider: getSupportedModels | `getSupportedModels()` | Retourneert `['sonnet' => 'Sonnet', 'opus' => 'Opus', 'haiku' => 'Haiku']` |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| RunAiJob: provider not found | Run met `provider='removed-provider'` | Run gemarkeerd als FAILED met "Provider 'removed-provider' is not configured" |
| RunAiJob: non-streaming provider | Run met mock provider die alleen `AiProviderInterface` implementeert | Sync `execute()` wordt aangeroepen; stream bestand bevat exact `{"type":"result","result":"sync result"}\n[DONE]\n` (geen `subtype` key) |
| RunAiJob: provider not found writes DONE | Run met `provider='removed-provider'` | Stream bestand bevat `[DONE]` zodat SSE relay niet blijft wachten |
| RunAiJob: streaming provider | Run met mock provider die `AiStreamingProviderInterface` implementeert | `executeStreaming()` wordt aangeroepen (bestaand gedrag) |
| Controller: unknown provider in request | Request met `provider='fake'` | Response: `['success' => false, 'error' => 'Invalid provider selection']`, HTTP 400. Server-side `Yii::warning()` gelogd |
| Controller: missing provider in request | Request zonder `provider` key | Default provider gebruikt; `$run->provider` is default identifier |
| Controller: cancel via stream token | `actionCancel()` met streamToken | `cancelProcess()` aangeroepen op alle providers; alleen de juiste retourneert `true` |
| Controller: cancelRun with specific provider | `actionCancelRun()` met run `provider='claude'` | `cancelProcess()` aangeroepen op Claude provider (uit run record) |
| Session cross-provider | Follow-up met `sessionId` en andere provider dan sessie-oorsprong | `$run->session_id` is `null` (nieuwe sessie gestart) |
| Backwards compat: AiProviderInterface injection | `Yii::$container->get(AiProviderInterface::class)` | Retourneert default provider uit registry |
| Concurrent jobs: different providers | 2 runs (provider='alpha', provider='beta') via `resolveProvider()` | Juiste mock provider instance per run geretourneerd |
| loadAiCommands: non-config provider | Default provider implementeert geen `AiConfigProviderInterface` | `loadAiCommands()` retourneert `[]` |
| Session cross-provider: controller query | Run met provider='claude' + session_id='ses-1', follow-up met provider='codex' + sessionId='ses-1' | Nieuwe run `session_id` is `null`; `forUser($userId)` scoping in query |

### Regressie-impact

| Bestaande functionaliteit | Risico | Mitigatie |
|--------------------------|--------|-----------|
| `ClaudeCliCompletionClient` | Laag — injecteert `AiProviderInterface` die via DI de default provider retourneert | Bestaande tests moeten ongewijzigd slagen |
| `AiQuickHandler` | Laag — ontvangt `AiCompletionClient` interface, geen directe provider-afhankelijkheid | Bestaande tests ongewijzigd |
| `AiChatController` constructor | Hoog — parameter wijzigt van `AiProviderInterface` naar `AiProviderRegistry` | Alle tests die de controller mocken moeten worden aangepast |
| `RunAiJob::createStreamingProvider()` | Hoog — methode wordt hernoemd naar `resolveProvider()` met andere signature | Bestaande RunAiJob tests moeten worden aangepast |
| `AiConfigProviderInterface` | Medium — nieuwe `getSupportedModels()` methode | `ClaudeCliProvider` (enige implementatie) moet methode toevoegen; bestaande tests uitbreiden |
| `loadAiCommands()` | Medium — gebruikt nu registry i.p.v. directe provider | Bestaande tests moeten `AiProviderRegistry` mock injecteren. Bij non-config provider: retourneert `[]` |

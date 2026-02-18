# Reviews

## Review: Architect — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Registry pattern past uitstekend bij bestaande DI-architectuur; lichtgewicht, geen lazy loading nodig
- Backwards compatibility via `AiProviderInterface` alias is correct: bestaande code (`ClaudeCliCompletionClient`, `AiQuickHandler`) blijft werken zonder wijziging
- Sync fallback in RunAiJob is essentieel en goed doordacht — niet alle providers zullen streaming ondersteunen
- Pre-rendering van provider data als JS object vermijdt onnodige AJAX calls en past bij het bestaande inline JS patroon
- Hergebruik van bestaande patronen (`instanceof` checks, `AiPermissionMode::labels()`, `Html::dropDownList`) is consistent

### Verbeterd
- **Kernprincipe contradictie opgelost**: Origineel claimde "geen interface wijzigingen" maar FR-6 voegde `getSupportedModels()` toe aan `AiConfigProviderInterface`. Nu expliciet vermeld dat dit de enige interface-uitbreiding is.
- **DI binding specificatie**: `AiProviderInterface` backwards compat binding nu concreet gespecificeerd als closure via `Yii::$container->get(AiProviderRegistry::class)->getDefault()` i.p.v. vaag "alias voor".
- **Controller constructor verduidelijkt**: "vervangt of vult aan" was ambigu; nu definitief "vervangt de huidige `AiProviderInterface` parameter".
- **RunAiJob DI context verduidelijkt**: Queue jobs worden door de queue worker geïnstantieerd zonder constructor DI. Nu expliciet dat registry via `Yii::$container->get()` wordt opgehaald, en methode hernoemd naar `resolveProvider(AiRun $run)`.
- **Sync fallback NDJSON formaat gespecificeerd**: Exact NDJSON event formaat voor sync fallback (`{"type":"result","result":"...","subtype":"text"}` + `[DONE]`).
- **Cross-provider session detectie gespecificeerd**: Controller query't eerste run van sessie om provider te vergelijken met geselecteerde provider.

### Nog open
- Geen

## Review: Security — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Provider identifier whitelist-validatie via registry is de juiste aanpak — geen arbitrary values mogelijk
- Bestaande RBAC-structuur (`behaviors()`, `findProject()` met `user_id` filter) blijft volledig intact
- `AiRun.provider` regex `/^[a-z][a-z0-9-]{1,48}$/` voorkomt injection via provider identifier
- Provider credential isolatie is goed gedocumenteerd — geen gedeelde credentials
- Frontend pre-rendering bevat geen gevoelige data (alleen namen, models, modes)

### Verbeterd
- **Security sectie toegevoegd**: Spec miste een expliciete security overwegingen sectie. Nu toegevoegd met 7 concerns en hun mitigaties.
- **`forUser()` scoping bij cross-provider session check**: De originele edge case specificeerde `AiRun::find()->forSession($sessionId)` zonder user scoping. Nu `forUser($userId)` toegevoegd om te voorkomen dat een gebruiker sessies van andere gebruikers kan resolven.
- **GET endpoint provider validatie verduidelijkt**: `actionUsage()` en `actionCheckConfig()` zijn GET requests. Provider parameter via query string wordt nu expliciet gevalideerd tegen registry whitelist met early return.
- **XSS mitigatie bij error messages**: Expliciet gemaakt dat provider identifiers in error messages door regex beperkt zijn en in views altijd met `Html::encode()` ge-escaped worden.

### Nog open
- Geen

## Review: UX/UI Designer — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Provider dropdown verbergen bij één provider is de juiste keuze — geen onnodige UI
- Pre-rendering van provider data voorkomt laadstaten bij provider wissel — instant feedback
- Hergebruik van bestaand `Html::dropDownList` patroon is consistent met rest van de view
- Accessibility attributen (aria-label, aria-live) zijn gespecificeerd
- Keyboard navigatie volgorde (Provider → Model → Permission Mode) is logisch

### Verbeterd
- **Wireframe uitgebreid**: Origineel miste de header met dynamische titel, chat bubbles met provider labels, en mobiel layout. Nu compleet met alle visuele lagen.
- **Mobiel responsiveness toegevoegd**: Provider dropdown wordt full-width boven Model en Permission Mode op mobiel. Combined bar stackt verticaal.
- **Chat bubble labels gespecificeerd**: Response bubbles tonen provider naam uit registry lookup i.p.v. hardcoded "Claude". Bron: `AiRun.provider` → registry.
- **Provider switch mid-sessie UX**: Inline waarschuwing "Switching provider will start a new session." toegevoegd. Geen blokkerend dialoog — lichte waarschuwing die na 5 seconden verdwijnt.
- **Model/permission mode reset bij provider wissel verduidelijkt**: Bestaande selecties resetten naar "(Use default)" als ze niet beschikbaar zijn bij de nieuwe provider.

### Nog open
- Geen

## Review: Front-end Developer — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Pre-rendering via `Json::encode()` is het standaard patroon in de codebase — geen nieuwe paradigma's
- `getOptions()` uitbreiding is minimaal — één property toevoegen
- Bestaande inline JS patroon wordt gevolgd (geen aparte modules)
- `repopulateSelect()` helper is een eenvoudige DOM operatie die consistent is met bestaande dropdown management

### Verbeterd
- **Provider data structuur gespecificeerd**: Origineel was alleen de conceptuele structuur benoemd. Nu concreet JSON formaat gedocumenteerd met alle velden: name, identifier, models, permissionModes, supportsUsage, supportsConfig.
- **Provider switch handler pseudo-code toegevoegd**: Concrete event handler met 5 stappen (model repopulatie, permission mode repopulatie, titel update, session waarschuwing, capability badges).
- **`getOptions()` uitbreiding expliciet getoond**: Code snippet met `provider` property toevoeging.
- **Backend provider data generatie toegevoegd**: PHP code snippet voor hoe `actionIndex` de provider data array opbouwt vanuit registry + `instanceof` checks.
- **`repopulateSelect()` helper gespecificeerd**: Duidelijke beschrijving van de functionaliteit (options verwijderen + opnieuw opbouwen vanuit map).

### Nog open
- Geen

## Review: Developer — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- `AiProviderRegistry` is een simpele, testbare service met constructor DI — past bij bestaand patroon
- Sync fallback in RunAiJob is goed gespecificeerd met exact NDJSON formaat
- `resolveProvider(AiRun $run)` methode-hernoem is beter dan parameter toevoegen aan `createStreamingProvider()`
- Backwards compat via `AiProviderInterface` closure is clean — bestaande code (`ClaudeCliCompletionClient`, `AiQuickHandler`) hoeft niet te wijzigen
- Queue job DI context correct beschreven — `Yii::$container->get()` i.p.v. constructor injectie

### Verbeterd
- **Concrete Yii2 DI config syntax toegevoegd**: Origineel beschreef alleen het concept. Nu concrete DI config met `Instance::of()` voor lazy resolution van named definitions, en closure voor `AiProviderInterface` backwards compat binding. Dit voorkomt circulaire instantiatie en past bij Yii2 DI patronen.
- **`Instance::of()` voor provider injectie**: Yii2's `Instance::of()` helper zorgt voor lazy resolution van named container definitions. Zonder dit zou de container de providers direct proberen te instantiëren bij registry-constructie, wat timing-problemen kan geven.

### Nog open
- Geen

## Review: Tester — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Registry unit tests zijn grondig: alle public methods + beide error cases (empty, duplicate)
- Edge case tests mappen 1-op-1 naar de edge case tabel — elke case heeft een test
- Sync fallback NDJSON formaat is concreet gespecificeerd — assertions zijn schrijfbaar
- Backwards compat test (`AiProviderInterface` injection) is een goede regressie-guard

### Verbeterd
- **Test file locaties toegevoegd**: Origineel ontbrak waar de test classes moeten leven. Nu concreet: `AiProviderRegistryTest` in `yii/tests/unit/services/ai/`, bestaande tests uitbreiden.
- **Regressie-impact tabel toegevoegd**: Identificeert 5 bestaande componenten met risico-inschatting (laag/medium/hoog) en mitigatiestrategie. Cruciaal voor implementatie-planning.
- **Error message assertions gespecificeerd**: Registry exception tests vermelden nu de verwachte message tekst ("Unknown provider: ...", "at least one provider").
- **RunAiJob streaming provider test toegevoegd**: Scenario voor provider die `AiStreamingProviderInterface` implementeert — verifiëren dat `executeStreaming()` wordt aangeroepen (bestaand gedrag-test).
- **HTTP status code bij unknown provider**: Controller test specificeert nu HTTP 400 bij onbekende provider.
- **Multiple providers test**: Extra unit test voor registry met 3 providers — verifiëren dat `all()` en `get()` correct werken met meerdere entries.

### Nog open
- Geen

---

# Ronde 4 — 2026-02-18

## Review: Architect Ronde 4 — 2026-02-18

### Score: 7/10 → 8/10 (na verbeteringen)

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Registry als immutable service elimineert concurrency-problemen. Read-only na constructie is een sterke garantie.
- Backwards compatibility via closure-alias is zorgvuldig uitgewerkt.
- Sync fallback met NDJSON is essentieel en goed gespecificeerd.
- Cross-provider session detectie via eerste-run query is correct en eenvoudig.

### Verbeterd
- **`actionCancel()` provider resolution opgelost**: Legacy endpoint heeft alleen `streamToken`, geen `runId`. Opgelost door `cancelProcess()` op alle providers aan te roepen (PIDs zijn uniek). `actionCancelRun()` resolvet via run record.
- **Permission modes filtering gecorrigeerd**: Backend code filtert nu `getSupportedPermissionModes()` subset via `array_intersect_key()` i.p.v. altijd alle modes te tonen.
- **Constructor pseudocode toegevoegd**: FR-1 toont nu hoe de numerieke array wordt omgezet naar identifier map via `getIdentifier()`.
- **RunAiJob `execute()` branching expliciet**: `instanceof AiStreamingProviderInterface` check nu concreet beschreven.
- **Combined bar provider type verduidelijkt**: Read-only badge, niet interactieve dropdown.
- **`actionSummarizeSession` hardcoded model/permissionMode**: Nu conditioneel op basis van default provider capabilities.

### Nog open
- Geen

## Review: Security Ronde 4 — 2026-02-18

### Score: 7/10 → 8/10 (na verbeteringen)

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Whitelist-validatie via registry + AiRun regex is een dubbele verdedigingslinie.
- Bestaande RBAC ongewijzigd — geen nieuwe aanvalsvectoren.
- `forUser()` scoping op session checks is defense-in-depth.
- Provider credential isolatie goed gedocumenteerd.

### Verbeterd
- **Security audit logging toegevoegd**: `Yii::warning()` bij ongeldige provider attempts en cross-provider session mismatches, met user context. Consistent met `.claude/rules/security.md`.
- **`buildSessionDialog` forUser scoping**: Query in RunAiJob nu scoped met `forUser($run->user_id)` naast `forSession()`.
- **Error messages defense-in-depth**: Generieke "Invalid provider selection" in response, identifier alleen server-side gelogd. Geen echo van user input.
- **`actionCancel()` verduidelijkt**: Legacy endpoint roept alle providers aan (PIDs uniek); `actionCancelRun()` resolvet via run record.
- **TOCTOU edge case gedocumenteerd**: Provider toegevoegd/verwijderd na run-creatie nu expliciet als edge case.

### Nog open
- Geen

## Review: UX/UI Designer Ronde 4 — 2026-02-18

### Score: 7/10 → 8/10 (na verbeteringen)

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Enkele provider verbergen blijft de juiste keuze.
- Pre-rendering voorkomt laadstaten bij wissel.
- Accessibility (aria-label, keyboard nav) is gespecificeerd.

### Verbeterd
- **Combined bar provider badge gespecificeerd**: Read-only `<span>` badge met `max-width: 120px` en `text-overflow: ellipsis` voor lange namen. Geplaatst als eerste badge. Geüpdatet in `updateSettingsSummary()`.
- **Session-waarschuwing persistent gemaakt**: Geen 5-sec auto-dismiss meer. Verdwijnt bij terugwisselen naar originele provider of na eerste send. Wordt vervangen (niet gestackt) bij snel wisselen.
- **Historische berichten met verwijderde provider**: Fallback met raw identifier als grijze, cursieve badge. Voorkomt JS runtime error.
- **Wireframe gecorrigeerd**: `<select>` dropdown i.p.v. radio buttons, consistent met `Html::dropDownList`.
- **Concrete aria-live element**: `<div id="ai-provider-status" class="visually-hidden" aria-live="polite">` toegevoegd.
- **Error message verbeterd**: "The selected provider is no longer available. Please refresh the page." i.p.v. technisch "Unknown provider: {id}".
- **Breadcrumbs verduidelijkt**: Server-side default naam, geen client-side update (te fragiel).

### Nog open
- Geen

## Review: Front-end Developer Ronde 4 — 2026-02-18

### Score: 7/10 → 8/10 (na verbeteringen)

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] Herbruikbare componenten geïdentificeerd met locatie

### Goed
- Pre-rendering via `Json::encode()` past bij bestaand patroon.
- `getOptions()` uitbreiding is minimaal.
- Inline JS patroon wordt gevolgd.

### Verbeterd
- **Element IDs hernoemd**: `claude-model` → `ai-model`, `claude-permission-mode` → `ai-permission-mode`. Provider-agnostisch. Alle referenties bijgewerkt.
- **`self` scope gecorrigeerd**: Handler is nu onderdeel van `setupEventListeners()` waar `var self = this` beschikbaar is.
- **`updateSettingsSummary()` aanroep toegevoegd**: Combined bar badges worden bijgewerkt na provider wissel.
- **Prototype pollution guard**: `Object.hasOwn(providerData, id)` check vóór data access.
- **`document.title` formaat**: Behoudt app naam suffix uit Yii2 layout via string split.
- **Timer cleanup gespecificeerd**: Persistente waarschuwing vervangt vorige (geen timer leaks).
- **Provider dropdown HTML**: Concrete `Html::dropDownList` met `id="ai-provider"`, `class="form-select"`, `aria-label`.
- **`updateCapabilityBadges()` gespecificeerd**: Beschrijving van gedrag voor config badge en usage bar.
- **`showProviderSwitchWarning()` gespecificeerd**: Persistent, vervangt vorige, verdwijnt bij terugwisselen of send.
- **Aria-live element**: Concreet `<div>` element toegevoegd met updatemechanisme.
- **Breadcrumbs**: Client-side update geschrapt (te fragiel). Server-side default.

### Nog open
- Geen

## Review: Developer Ronde 4 — 2026-02-18

### Score: 7/10 → 8/10 (na verbeteringen)

### 8+ Checklist
- [x] Geen interne contradicties tussen secties
- [x] Alle componenten hebben concrete file locaties
- [x] UI states gespecificeerd (loading, error, empty, success)
- [x] Security validaties expliciet per endpoint
- [x] Wireframe-component alignment
- [x] Test scenarios dekken alle edge cases
- [x] DI configuratie is correct implementeerbaar met Yii2 container

### Goed
- Spec is na 3 review rondes functioneel compleet: alle FR's, edge cases, security en tests zijn gedocumenteerd
- `resolveProvider(AiRun $run)` in RunAiJob via `Yii::$container->get()` is correct voor queue context
- Backwards compat via closure binding voor `AiProviderInterface` is clean en behoudt bestaand gedrag
- Sync fallback NDJSON formaat (`{"type":"result","result":"...","subtype":"text"}` + `[DONE]`) is concreet en past bij bestaand `extractResultText()` patroon
- Cross-provider session detectie via eerste-run provider vergelijking is correct gespecificeerd
- `actionCancel()` resolvet provider uit run record — niet uit request body — dit is de juiste aanpak

### Verbeterpunten

**1. KRITIEK: `Instance::of()` werkt NIET in geneste arrays zonder `setResolveArrays(true)`**

De DI config in FR-2 specificeert:
```php
'__construct()' => [
    'providers' => [
        Instance::of('aiProvider.claude'),
    ],
],
```
Yii2's `Container::resolveDependencies()` (regel 592-606 in `vendor/yiisoft/yii2/di/Container.php`) itereert over dependencies en resolvet `Instance` objecten. Maar voor geneste arrays (een array binnen een dependency) controleert het `$this->_resolveArrays` (regel 603). Deze property is standaard `false`. Dit project roept nergens `setResolveArrays(true)` aan. Dit betekent dat de registry constructor een array ontvangt met ongeresolvede `Instance` objecten in plaats van echte `AiProviderInterface` instances.

**Oplossing A** (aanbevolen): Gebruik een closure als definition voor de registry:
```php
AiProviderRegistry::class => function () {
    return new AiProviderRegistry([
        Yii::$container->get('aiProvider.claude'),
    ]);
},
```

**Oplossing B**: Activeer array resolution globaal:
```php
Yii::$container->setResolveArrays(true);
```
Dit is minder wenselijk omdat het onbedoelde bijwerkingen kan hebben op andere DI definitions.

**2. Registry constructor signature: `array $providers` type is ambigu**

De spec zegt "Constructor accepteert `array $providers`" maar definieert niet of de keys provider identifiers zijn of dat identifiers uit `getIdentifier()` worden gelezen. De spec's acceptatiecriteria impliceren het laatste (mapping op identifier bij registratie, duplicate check), maar dit is niet expliciet. Bij implementatie moet duidelijk zijn:
- Constructor ontvangt een numeriek-geïndexeerde array van `AiProviderInterface` instances
- De registry bouwt intern de identifier-map via `$provider->getIdentifier()`

Dit is impliciet correct in de spec (duplicate check via identifier), maar verdient een expliciete vermelding.

**3. `resolveProvider()` caching in RunAiJob**

De spec zegt `Yii::$container->get(AiProviderRegistry::class)` per `resolveProvider()` call. Yii2's DI container cached singletons pas als ze als singleton geregistreerd zijn (`setSingleton()`). De config in FR-2 registreert de registry als gewone definition in `container.definitions`, dus elke `get()` call creëert een nieuwe instance inclusief nieuwe provider instances.

Functioneel niet fout (providers zijn lichtgewicht — `ClaudeCliProvider` constructor maakt alleen een `CopyFormatConverter`), maar suboptimaal. In queue context draait `resolveProvider()` slechts eenmaal per job, dus de impact is minimaal.

**Aanbeveling**: Registreer als singleton of cache in een property. Niet blokkerend.

**4. Provider constructor exceptions: fail-fast is correct maar ongedocumenteerd**

De spec benoemt registry-level exceptions (lege array, duplicates), maar niet wat er gebeurt als een provider constructor faalt. Met Oplossing A (closure) zou een provider constructor exception propageren bij eerste registry-resolution, niet bij boot. Met de originele `Instance::of()` aanpak zou het bij boot falen. Het verschil is relevant: fail-at-boot is strenger maar veiliger.

De huidige `ClaudeCliProvider` constructor is tolerant (`?CopyFormatConverter $formatConverter = null`), dus dit is laag risico nu. Documenteer het verwachte gedrag voor toekomstige providers.

**5. `actionCancel()` mist specificatie voor provider resolution**

De spec (FR-3) zegt: "`actionCancel()` resolvet de provider uit de `provider` kolom van de run". Maar de huidige `actionCancel()` implementatie (regel 345-366) ontvangt alleen een `streamToken` en een project ID — geen `runId`. De run is niet opvraagbaar via streamToken (niet opgeslagen op de run).

Er zijn twee mogelijkheden:
- De provider identifier moet meegestuurd worden in het cancel request body (extra parameter, niet in spec)
- Legacy cancel gebruikt altijd de default provider (current behavior, safe voor single-provider)

Het async cancel pad (`actionCancelRun()`) heeft dit probleem niet — het ontvangt `runId`, kan de run laden, en de `provider` kolom uitlezen. Maar `actionCancelRun()` gebruikt geen provider resolution: het zet alleen de DB status op cancelled.

**Conclusie**: `actionCancel()` hoeft de provider feitelijk alleen te resolven om `cancelProcess()` aan te roepen (PID kill). Als de legacy cancel uitfaseert naar `actionCancelRun()` (die geen process kill doet maar alleen DB-status wijzigt), is dit een non-issue. De spec moet verduidelijken welk cancel-pad primair is.

**6. Sync fallback NDJSON: `subtype` key is overbodig**

De spec definieert sync fallback als `{"type":"result","result":"...","subtype":"text"}`. In het bestaande `extractResultText()` (RunAiJob regel 153-171) en `extractMetadata()` (regel 177-199) wordt alleen `type === 'result'` gecheckt. De `subtype` key wordt nergens geconsumeerd in backend of frontend parsing. Verwijder deze key uit de spec om verwarring te voorkomen, of documenteer het doel.

**7. `getSupportedModels()` return type inconsistentie**

FR-6 specificeert `getSupportedModels(): array` retourneert `[value => label]` map. De test in de spec verwacht `['sonnet' => 'Sonnet', 'opus' => 'Opus', 'haiku' => 'Haiku']`. Maar in de provider data generatie (backend snippet) wordt `array_merge(['' => '(Use default)'], $provider->getSupportedModels())` gebruikt. Dit werkt correct met string keys. Consistent, geen probleem.

Echter: de `AiConfigProviderInterface` bestaande methode `getSupportedPermissionModes()` retourneert `string[]` (numerieke keys met waarden). Het nieuwe `getSupportedModels()` retourneert `array<string, string>` (assocatieve map). Dit is een inconsistentie in het interface-ontwerp. De permission modes komen als waarden, de models als key-value pairs. De spec hanteert dit correct in de provider data generatie (permission modes worden door `AiPermissionMode::labels()` gemapped), maar het is een subtiel verschil dat bij implementatie verwarring kan geven.

### Verbeterd
- **DI config herschreven naar closure**: `Instance::of()` vervangen door `Yii::$container->get()` binnen een closure. Voorkomt het `setResolveArrays()` probleem volledig.
- **Singleton registratie**: Registry wordt als singleton geregistreerd via `setSingleton()`.
- **Constructor pseudocode toegevoegd**: Numeriek-geïndexeerde array met `getIdentifier()` mapping nu expliciet.
- **`subtype` key verwijderd**: Sync fallback NDJSON is nu `{"type":"result","result":"..."}` zonder overbodige `subtype`.
- **`actionCancel()` verduidelijkt**: Legacy endpoint roept alle providers aan; `actionCancelRun()` resolvet via run record.

### Nog open
- Geen

## Review: Tester Ronde 4 — 2026-02-18

### Score: 8/10

### 8+ Checklist
- [x] Acceptatiecriteria meetbaar en automatiseerbaar
- [x] Happy path tests voor alle FR's aanwezig
- [x] Error path tests voor registry, controller, job aanwezig
- [x] Edge cases gedocumenteerd met verwachte resultaten
- [x] Regressie-impact geïdentificeerd met mitigatiestrategie
- [x] Test file locaties concreet gespecificeerd

### Goed
- Regressie-dekking RunAiJob is sterk: bestaande tests gebruiken anonymous class override patroon.
- Registry tests zijn compleet voor de public API: 10+ unit tests.
- Controller backwards compat test is een goede regressie-guard.
- Cancel-with-wrong-provider edge case vangt subtiele bug.

### Verbeterd
- **Registry insertion order test toegevoegd**: `array_keys($registry->all())` moet identiek zijn aan registratie-volgorde.
- **NDJSON sync fallback formaat gecorrigeerd**: `subtype` key verwijderd (inconsistent met bestaande parsing). Exacte bytes gespecificeerd: `{"type":"result","result":"sync result"}\n[DONE]\n`.
- **Provider not found writes DONE test**: Verifieert dat `[DONE]` naar stream bestand wordt geschreven bij onbekende provider.
- **Concurrent jobs test**: 2 runs met verschillende providers via `resolveProvider()`.
- **`loadAiCommands` met non-config provider test**: Retourneert `[]`.
- **Session cross-provider controller-level test**: Verifieert `session_id` null na provider switch inclusief `forUser()` scoping.
- **Regressie-impact tabel uitgebreid**: `loadAiCommands()` toegevoegd (medium risico).

### Nog open
- Geen

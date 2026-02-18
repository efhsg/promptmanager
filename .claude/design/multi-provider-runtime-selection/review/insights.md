# Insights

## Codebase onderzoek

### Vergelijkbare features
- AI Provider Abstraction Layer: `.claude/design/AI-provider-abstraction-layer/spec.md` — vorige feature die interfaces definieerde + `ai_run` tabel + provider column. Volledig geïmplementeerd en vormt de basis voor dit werk.
- AiPermissionMode enum: `yii/common/enums/AiPermissionMode.php` — enum met `values()` en `labels()` methoden; `ClaudeCliProvider::getSupportedPermissionModes()` retourneert `AiPermissionMode::values()`

### Herbruikbare componenten
- `AiPermissionMode::labels()` — kan direct hergebruikt worden voor permission mode dropdown, is al provider-agnostisch
- `Html::dropDownList` — bestaand patroon in de view voor model/permission mode dropdowns
- `AiProviderInterface::getIdentifier()` — identifier al aanwezig en gevalideerd in `AiRun.rules()` met regex `/^[a-z][a-z0-9-]{1,48}$/`
- `AiRun.provider` column — al bestaand, VARCHAR(50), default 'claude'
- `AiCompletionClient` interface — al abstract, niet Claude-specifiek

### Te volgen patterns
- DI container configuratie: `yii/config/main.php` met `$config['container']['definitions']`
- Constructor DI: Alle controllers en services gebruiken constructor injection
- `instanceof` checks: Controller controleert optional interfaces via instanceof (bijv. `$this->aiProvider instanceof AiUsageProviderInterface`)
- AJAX response format: `['success' => bool, 'message'/'error' => string, 'data' => mixed]`
- View data via controller params: `actionIndex` geeft view variabelen mee via `$this->render('index', [...])`

### Bevindingen
1. **ClaudeCliCompletionClient** is al gekoppeld via `AiProviderInterface` (niet direct aan Claude). De class naam is misleidend maar de implementatie is provider-agnostisch via DI — het roept `$this->claudeCliService->execute()` aan waar `claudeCliService` een `AiProviderInterface` is.
2. **RunAiJob::createStreamingProvider()** op lijn 320-323 lost de provider op via `Yii::$container->get(AiProviderInterface::class)` — dit negeert het `provider` veld op de run. Kritieke fix nodig.
3. **View hardcodings** op lijn 56-72: permission modes en models zijn hardcoded PHP arrays. Titel is hardcoded "Claude CLI".
4. **getOptions()** JS functie (lijn 849-853) stuurt alleen `model` en `permissionMode` mee, geen `provider`.
5. **`AiConfigProviderInterface`** heeft al `getSupportedPermissionModes()` maar mist `getSupportedModels()`.
6. **`AiStreamRelayService`** is volledig provider-agnostisch — geen wijzigingen nodig.
7. **Session cross-provider validatie**: `session_id` komt van de provider's CLI, niet van de applicatie. Een Claude session_id meegeven aan Codex is zinloos maar niet gevaarlijk (provider negeert het).

## Beslissingen
- FR-3 beschrijving gecorrigeerd: "naast (of in plaats van)" → "in plaats van" voor consistentie met acceptatiecriteria
- Eén interface-uitbreiding toegestaan: `getSupportedModels()` op `AiConfigProviderInterface` — expliciet gedocumenteerd als uitzondering op het kernprincipe
- Queue jobs gebruiken `Yii::$container->get(AiProviderRegistry::class)` i.p.v. constructor DI
- Provider data wordt inline gerenderd als JS object om extra AJAX calls te vermijden
- Cross-provider session check bevat `forUser($userId)` scoping voor security

## Consistentiecheck (ronde 3)
- 1 contradictie gevonden en gecorrigeerd (FR-3 beschrijving vs criterium)
- Alle overige checks geslaagd: wireframe↔componenten, frontend↔backend, edge cases↔tests, architectuur↔locaties, security↔endpoints

## Ronde 4 — Beslissingen
- DI config herschreven van `Instance::of()` in arrays naar closure-based definition (Yii2 `setResolveArrays` niet actief)
- Registry als singleton geregistreerd via `setSingleton()`
- `actionCancel()` (legacy) roept `cancelProcess()` op alle providers aan (PIDs uniek); `actionCancelRun()` resolvet via run record
- Error responses bevatten generieke "Invalid provider selection" — identifier niet terug-ge-echod (defense-in-depth)
- Security audit logging toegevoegd (`Yii::warning()` bij ongeldige provider attempts)
- `buildSessionDialog` scoped met `forUser($run->user_id)` (defense-in-depth)
- Sync fallback NDJSON: `subtype` key verwijderd (niet geconsumeerd door bestaande parsing)
- Permission modes gefilterd via `getSupportedPermissionModes()` + `array_intersect_key()`
- Element IDs hernoemd: `claude-model` → `ai-model`, `claude-permission-mode` → `ai-permission-mode`
- Session-waarschuwing persistent i.p.v. 5-sec auto-dismiss
- Combined bar provider: read-only badge met `max-width: 120px` en `text-overflow: ellipsis`
- Breadcrumbs: server-side default, geen client-side update (te fragiel)
- `actionSummarizeSession`: conditionele model/permissionMode op basis van default provider capabilities
- `loadAiCommands`: altijd default provider bij page load (GET), retourneert `[]` bij non-config provider

## Consistentiecheck (ronde 4)
- 0 contradicties gevonden
- 10 cross-checks uitgevoerd: error responses↔edge cases↔tests, sync fallback↔tests, actionCancel↔edge cases↔endpoints↔tests, DI config↔singleton, wireframe↔frontend↔accessibility, element IDs, session waarschuwing, security logging, buildSessionDialog forUser, permission modes filtering

## Open vragen
- (geen)

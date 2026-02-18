# Insights

## Codebase onderzoek

### Vergelijkbare features
- AI Provider infrastructure: `yii/services/ai/` — volledig interface-gebaseerd, 5 interfaces (`AiProviderInterface`, `AiStreamingProviderInterface`, `AiWorkspaceProviderInterface`, `AiUsageProviderInterface`, `AiConfigProviderInterface`)
- `ClaudeCliProvider`: `yii/services/ai/providers/ClaudeCliProvider.php` — implementeert alle 5 interfaces, enige concrete provider
- `AiProviderRegistry`: `yii/services/ai/AiProviderRegistry.php` — immutable singleton, indexeert providers op identifier

### Herbruikbare componenten
- `AiProviderRegistry` — al klaar voor meerdere providers (accepteert array)
- `AiProviderInterface` + sub-interfaces — al gedefinieerd, hoeven niet te veranderen
- Chat view provider selector — al aanwezig (`#ai-provider` select, conditionally getoond bij >1 provider)
- `buildProviderData()` in `AiChatController` — al provider-aware, itereert registry
- `prepareRunRequest()` — leest provider uit POST, valideert cross-provider session continuity
- `RunAiJob` — resolved provider via registry, ondersteuning streaming + sync fallback

### Te volgen patterns
- DI: singletons voor providers in `yii/config/main.php`, registry als singleton
- Provider registratie: `'aiProvider.{id}' => ProviderClass::class` in singletons
- Controller: constructor injection van `AiProviderRegistry`
- Views: provider data als JSON via Alpine.js state
- Forms: `ai_options[key]` naamgeving voor form fields

### Huidige beperkingen gevonden
- **`prepareRunRequest()`**: hardcoded `allowedKeys` whitelist — provider-specifieke keys worden gefilterd
- **Project form**: hardcoded "Claude CLI Defaults" card — niet dynamisch vanuit provider
- **`RunAiJob`**: drie private methoden die Claude-specifiek NDJSON parsen — dubbel met `ClaudeCliProvider::parseStreamResult()`
- **`Project::getAiOptions()`**: flat JSON, niet genamespaced per provider
- **`AiConfigProviderInterface`**: mist `getConfigSchema()` methode
- **Frontend JS**: Claude-specifieke event handlers

## Beslissingen tijdens review

1. **`getDefaultProvider()` fallback**: Hardcoded `'claude'` i.p.v. registry lookup — model mag niet afhankelijk zijn van services (Architect review)
2. **`loadAiOptions()` provider key validatie**: POST data keys worden gevalideerd tegen `AiProviderRegistry::has()` — voorkomt arbitraire key injectie (Security review)
3. **`buildCommand()` escapeshellarg()**: Expliciet vereist voor alle user-controlled values bij elke provider (Security review)
4. **Meta events gescheiden van provider events**: `waiting`, `keepalive`, `prompt_markdown`, `run_status`, `server_error`, `sync_result` worden verwerkt VOOR provider dispatch (Front-end review)
5. **`isNamespacedOptions()` heuristic**: Flat options hebben altijd scalar values; namespaced options hebben minimaal 1 array value met geldige provider identifier als key (Developer review)
6. **Regressie-impact**: 4 bestaande test files geïdentificeerd die mogelijk aangepast moeten worden (Tester review)

## Beslissingen tijdens review ronde 2

7. **`ProjectController` DI injection expliciet**: Constructor wijzigt van `AiProviderInterface` naar `AiProviderRegistry` — expliciet als acceptatiecriterium (Architect review ronde 2)
8. **`actionAiCommands()` provider parameter**: AJAX endpoint accepteert optionele provider parameter, gevalideerd tegen registry (Architect + Security review ronde 2)
9. **`afterDelete()` handelt workspace cleanup af**: Niet de controller — voorkomt dubbele cleanup (Architect review ronde 2)
10. **XSS-preventie bij configSchema rendering**: Labels/hints/options ge-escaped via `Html::encode()` of DOM `textContent` (Security review ronde 2)
11. **Command dropdown lazy-loading**: Per-tab AJAX fetch bij eerste activatie, niet eager (Front-end review ronde 2)
12. **`setAiOptionsForProvider()` filtert lege values**: Consistent met bestaand `setAiOptions()` gedrag (Developer review ronde 2)
13. **`getAiCommandBlacklist/Groups` optionele provider parameter**: `?string $provider = null` voor backward-compat (Developer review ronde 2)
14. **Regressie-impact uitgebreid**: 5 bestaande test files geïdentificeerd (was 4) (Tester review ronde 2)

## Open vragen
- Geen

## Blokkades
- Geen

## Eindresultaat ronde 1
Spec review voltooid met 6/6 reviews >= 8/10. Consistentiecheck passed zonder contradicties.

## Eindresultaat ronde 2
Spec review voltooid met 6/6 reviews >= 9/10. Consistentiecheck passed met 1 inconsistentie gecorrigeerd (componenten tabel vs FR-6 m.b.t. `actionDelete()` workspace cleanup). Spec is implementatie-klaar.

# Code Review Context

## Change
Multi-provider runtime selectie: de hardcoded koppeling aan ClaudeCliProvider is vervangen door een AiProviderRegistry die meerdere AI-providers beheert. De controller, job en view zijn aangepast zodat de gebruiker runtime een provider kan kiezen via een dropdown. Cross-provider sessiedetectie voorkomt dat een sessie over providers heen loopt.

## Scope
| Bestand | Samenvatting |
|---------|-------------|
| `yii/services/ai/AiProviderRegistry.php` | Nieuw — Registry die providers indexeert op identifier |
| `yii/services/ai/AiConfigProviderInterface.php` | Nieuwe methode `getSupportedModels()` |
| `yii/services/ai/providers/ClaudeCliProvider.php` | Implementatie van `getSupportedModels()` |
| `yii/config/main.php` | DI-container met registry singleton |
| `yii/controllers/AiChatController.php` | Provider-selectie per request, nieuwe helpers |
| `yii/jobs/RunAiJob.php` | Provider resolving via registry, sync fallback |
| `yii/views/ai-chat/index.php` | Provider dropdown, dynamische UI |
| `yii/tests/unit/services/ai/AiProviderRegistryTest.php` | Nieuw — 10 tests voor registry |
| `yii/tests/unit/controllers/AiChatControllerTest.php` | Aangepast voor registry |
| `yii/tests/unit/jobs/RunAiJobTest.php` | Gerefactored + 3 nieuwe tests |

## Type
Nieuwe feature (full-stack)

## Reviewvolgorde
1. Reviewer
2. Architect
3. Security
4. Front-end Developer
5. Developer
6. Tester

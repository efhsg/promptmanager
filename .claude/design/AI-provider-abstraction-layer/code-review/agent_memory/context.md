# Code Review Context

## Change
Volledige implementatie van de AI Provider Abstraction Layer: hardcoded Claude-afhankelijkheden zijn geabstraheerd naar een interface-gebaseerd provider systeem. Dit omvat 5 nieuwe interfaces, een concrete ClaudeCliProvider, 3 database migraties, hernoemde models/services/controllers/views/tests, en DI-wiring.

## Scope
84 bestanden gewijzigd (3701+, 3049-):
- Interfaces (5 nieuw): AiProviderInterface, AiStreamingProviderInterface, AiWorkspaceProviderInterface, AiUsageProviderInterface, AiConfigProviderInterface
- Provider (1 nieuw): ClaudeCliProvider — refactor van ClaudeCliService + ClaudeWorkspaceService
- Migraties (3 nieuw): Tabel/kolom rename, RBAC rename
- Models (4 hernoemd): AiRun, AiRunQuery, AiRunSearch, AiRunOwnerRule
- Enums (2 hernoemd): AiRunStatus, AiPermissionMode
- Services (2 hernoemd): AiStreamRelayService, AiRunCleanupService
- Job (1 hernoemd): RunAiJob
- Handler (1 hernoemd): AiQuickHandler
- Controllers (3 hernoemd): AiChatController, AiRunController, AiController
- Views (3 hernoemd + 13 gewijzigd): ai-chat/ directory + integratie-views
- CSS (1 hernoemd): ai-chat.css
- Tests (17 hernoemd/gewijzigd): Alle test files en fixtures
- Config (3 gewijzigd): main.php, rbac.php, bootstrap
- Verwijderd: ClaudeCliService, ClaudeWorkspaceService + oude test files

## Type
Full-stack refactoring

## Reviewvolgorde
1. Reviewer
2. Architect
3. Security
4. Front-end Developer
5. Developer
6. Tester

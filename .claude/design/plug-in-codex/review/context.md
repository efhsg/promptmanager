# Context

## Doel
Schrijf een implementatie-klare specificatie voor het inpluggen van Codex CLI als tweede AI provider in PromptManager.

## Scope
- Plugin-architectuur voor CLI providers
- Per-provider project configuratie (tabs in project form)
- Provider-specifieke opties declaratie, opslag en doorstroming
- Workspace management per provider
- Frontend event abstractie voor streaming
- Codex CLI als proof-of-concept provider

## User Story
Schrijf een gedetailleerde specificatie voor het inpluggen van Codex CLI gebaseerd op de inzichten in `.claude/design/pluggable-cli-providers/feature_desc.md`.

## Bronnen
- Feature beschrijving: `.claude/design/pluggable-cli-providers/feature_desc.md`
- Codebase analyse: `.claude/codebase_analysis.md`
- Bestaande AI provider code: `yii/services/ai/`

# Context

## Doel
Introduceer een provider-abstractielaag zodat nieuwe AI CLI providers (Codex, Gemini) kunnen worden aangesloten door een interface te implementeren en te registreren in de DI-container — zonder bestaande controller-, view- of modelcode aan te passen.

## Scope
- Abstractie van hardcoded Claude-afhankelijkheden in 22+ bestanden
- Interface-gebaseerd provider systeem
- DI-container registratie
- Geen wijzigingen aan bestaande controllers/views/models (enkel hernoemen/abstraheren)

## User Story
AI Provider Abstraction Layer — see spec for full details.

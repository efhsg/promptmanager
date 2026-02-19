# Context

## Doel
Verbeter de Provider Selection UX in de AI Chat view zodat:
- Settings makkelijk vindbaar en bewerkbaar zijn vóór het starten van een conversatie
- Het visueel duidelijk is wanneer settings gelocked zijn (actieve sessie)
- Het pad naar "New Session" ontdekbaar is
- Interactief-uitziende elementen die eigenlijk disabled zijn worden vermeden

## Scope
- `yii/views/ai-chat/index.php` — view + inline JS
- `yii/web/css/ai-chat.css` — styling combined bar + settings card
- Geen backend wijzigingen vereist (puur frontend UX)

## User Story
Zie `.claude/design/provider-selection-ux/prompt.md`

## Kernproblemen
1. Settings verborgen op page load → gebruiker weet niet dat combined bar klikbaar is
2. Na eerste send: settings collapsed + locked → uitklappen toont disabled dropdowns zonder uitleg
3. Geen visuele lock-indicator op combined bar
4. Combined bar is overloaded (project context + settings + config + toggle)
5. `newSession()` auto-expand kan jarring zijn

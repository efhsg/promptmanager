# Code Review Context — Ronde 3

## Change
Drie commits na review ronde 2: (1) Nieuwe ChoiceOptionParser helper + JS verbeteringen, (2) RunAiJob tests, (3) Error suppression stream file + label hernoemingen Claude → AI.

## Scope
- `yii/helpers/ChoiceOptionParser.php` — Nieuw: PHP parser voor keuzebutton-patronen
- `yii/tests/unit/helpers/ChoiceOptionParserTest.php` — Nieuw: 338 regels tests
- `yii/tests/unit/jobs/RunAiJobTest.php` — +195 regels: 5 nieuwe testmethoden
- `yii/jobs/RunAiJob.php` — Error suppression (`@`) op file I/O, label hernoemingen
- `yii/services/EntityPermissionService.php` — MODEL_BASED_ACTIONS opgeschoond
- `yii/views/ai-chat/index.php` — JS: em-dash prefix stripping, maxlength 30→80
- `yii/views/note/index.php` — {claude} → {ai}, CSS class hernoemd
- `yii/views/note/view.php` — Variabelen en labels Claude → AI
- `yii/views/prompt-instance/index.php` — {claude} → {ai}, CSS class hernoemd
- `yii/views/prompt-instance/view.php` — Variabelen en labels Claude → AI
- `yii/controllers/ProjectController.php` — $claudeOptions → $aiOptions
- `yii/handlers/AiQuickHandler.php` — Log label hernoemd
- `yii/commands/AiRunController.php` — Stdout tekst hernoemd

## Type
Full-stack (backend + frontend + tests)

## Focus
Robuustheid van de communicatie tussen PromptManager en de Claude CLI

## Reviewvolgorde
1. Reviewer
2. Architect
3. Security
4. Developer
5. Front-end Developer
6. Tester

## Diff range
87c2a11..b3e1da5
